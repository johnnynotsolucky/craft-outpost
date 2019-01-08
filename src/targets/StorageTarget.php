<?php
namespace johnnynotsolucky\outpost\targets;

use Craft;
use johnnynotsolucky\outpost\Plugin;
use craft\db\Query;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;

class StorageTarget extends Target
{
    private $items = [
        Plugin::TYPE_REQUEST => [],
        Plugin::TYPE_EXCEPTION => [],
        Plugin::TYPE_LOG => [],
        Plugin::TYPE_EVENT => [],
        Plugin::TYPE_PROFILE => []
    ];

    private $hasException = false;

    public $logVars = [];

    public function addStorageHandler($handler)
    {
        $this->storageHandlers[] = $handler;
    }

    public function addItem($item)
    {
        if ($item['type'] === Plugin::TYPE_EXCEPTION) {
            $this->hasException = true;
        }

        $item['requestId'] = Plugin::getRequestId();
        if (!isset($item['timestamp'])) {
            $item['timestamp'] = time();
        }

        if ($item['type'] === Plugin::TYPE_REQUEST) {
            // Always have only a single item for the Request
            $this->items[Plugin::TYPE_REQUEST] = [$item];
        } else {
            $this->items[$item['type']][] = $item;
        }
    }

    private function isInSample($request, $requestSampling)
    {
        if ($requestSampling) {
            $hash = $request['hash'];
            $key = "request_{$hash}";
            $cache = Craft::$app->getCache();
            $cached = $cache->get($key);

            if (!$cached) {
                $cached = [
                    'start_time' => time(),
                    'previous_count' => 0,
                    'count' => 0,
                ];

                $cache->set($key, $cached);
            }

            $now = time();
            if ($now - $cached['start_time'] > 600) { // 10 min sample intervals
                $cached['previous_count'] = $cached['count'];
                $cached['count'] = 0;
                $cached['start_time'] = $now;
            }

            $cached['count'] += 1;
            $cache->set($key, $cached);

            if ($cached['previous_count'] > 0) {
                $sampleRate = (int) ceil($cached['previous_count'] * 0.05);

                if ($cached['count'] % $sampleRate === 0) {
                    return true;
                }

            }

            return false;
        }

        return true;
    }

    public function export()
    {
        $settings = Plugin::getInstance()->getSettings();

        $requestData = $this->saveRequest();

        if ($this->isInSample($requestData, $settings->requestSampling)) {
            $profiling = array();

            foreach ($this->messages as $message) {
                list($text, $level, $category, $timestamp) = $message;

                if (
                    (!$this->hasException && $level <= $settings->minimumLogLevel)
                    || ($this->hasException && $level <= $settings->minimumExceptionLogLevel)
                ) {
                    $message = is_string($text) ? $text : json_encode($text);

                    // Try remove some noise from the logs
                    $matched = [];
                    if (!preg_match('/_outpost_/', $message, $matched)) {
                        $this->addItem([
                            'type' => Plugin::TYPE_LOG,
                            'timestamp' => (int) $timestamp,
                            'message' => $message,
                            'level' => $this->getLevelName($level),
                            'category' => $category
                        ]);
                    }
                }

                if (
                    $level === Logger::LEVEL_PROFILE ||
                    $level === Logger::LEVEL_PROFILE_BEGIN ||
                    $level === Logger::LEVEL_PROFILE_END
                ) {
                    $matched = [];
                    if (!preg_match('/_outpost_/', $message[0], $matched)) {
                        $profiling[] = $message;
                    }
                }
            }

            $timings = Craft::getLogger()->calculateTimings($profiling);

            foreach ($timings as $seq => $timing) {
                $this->addItem([
                    'type' => Plugin::TYPE_PROFILE,
                    'duration' => $timing['duration'] * 1000, // in milliseconds
                    'category' => $timing['category'],
                    'info' => $timing['info'],
                    'level' => $this->getLevelName($timing['level']),
                    'seq' => $seq,
                ]);
            }

            foreach ($this->items as $key => $items) {
                if ($key === Plugin::TYPE_REQUEST) {
                    if (sizeof($items) > 0) {
                        $id = (new Query())
                            ->select(['id'])
                            ->from('{{%outpost_requests}}')
                            ->where(['requestId' => Plugin::getRequestId()])
                            ->scalar();

                        $command = Craft::$app->db->createCommand();
                        if ($id) {
                            $command->update('{{%outpost_requests}}', $items[0], ['id' => $id]);
                        } else {
                            $command->insert('{{%outpost_requests}}', $items[0]);
                        }
                        $command->execute();
                    }
                } else {
                    $table = Plugin::TABLES[$key];

                    $items = array_map(function ($item) use ($key) {
                        $tmp = [];
                        foreach (Plugin::TABLES[$key]['columns'] as $column) {
                            $tmp[] = $item[$column];
                        }
                        return $tmp;
                    }, $items);

                    Craft::$app->db->createCommand()
                        ->batchInsert(
                            $table['table'],
                            $table['columns'],
                            $items
                        )
                        ->execute();
                }
                $this->items[$key] = [];
            }

            // Purge
            $purgeLimit = $settings->purgeLimit;
            if ($purgeLimit > 0) {
                $queryA = (new Query())
                    ->select(['requestId'])
                    ->from('{{%outpost_requests}}')
                    ->orderBy(['timestamp' => SORT_DESC])
                    ->groupBy('requestId')
                    ->limit($purgeLimit);

                $queryB = (new Query())
                    ->select(['requestId'])
                    ->from(['r' => $queryA]);

                foreach (Plugin::TABLES as $key => $table) {
                    if ($key !== Plugin::TYPE_REQUEST) {
                        Craft::$app->db->createCommand()
                            ->delete($table['table'], ['not in', 'requestId', $queryB])
                            ->execute();
                    }
                }

                Craft::$app->db->createCommand()
                    ->delete('{{%outpost_requests}}', ['not in', 'requestId', $queryB])
                    ->execute();
            }
        }
    }

    private function saveRequest()
    {
        $response = Craft::$app->response;
        $response = is_string($response->data)
            ? $response->data
            : json_encode($response->data);

        $headers = Craft::$app->request->getHeaders();
        $requestHeaders = [];
        foreach ($headers as $name => $value) {
            if (is_array($value) && count($value) == 1) {
                $requestHeaders[$name] = current($value);
            } else {
                $requestHeaders[$name] = $value;
            }
        }

        $responseHeaders = [];
        foreach (headers_list() as $header) {
            if (($pos = strpos($header, ':')) !== false) {
                $name = substr($header, 0, $pos);
                $value = trim(substr($header, $pos + 1));
                if (isset($responseHeaders[$name])) {
                    if (!is_array($responseHeaders[$name])) {
                        $responseHeaders[$name] = [$responseHeaders[$name], $value];
                    } else {
                        $responseHeaders[$name][] = $value;
                    }
                } else {
                    $responseHeaders[$name] = $value;
                }
            } else {
                $responseHeaders[] = $header;
            }
        }
        if (Craft::$app->requestedAction) {
            if (Craft::$app->requestedAction instanceof InlineAction) {
                $action = get_class(Craft::$app->requestedAction->controller) . '::' . Craft::$app->requestedAction->actionMethod . '()';
            } else {
                $action = get_class(Craft::$app->requestedAction) . '::run()';
            }
        } else {
            $action = null;
        }

        parse_str(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '', $querystring);

        $data = [
            'type' => Plugin::TYPE_REQUEST,
            'hostname' => $_SERVER['HTTP_HOST'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'path' => $_SERVER['REQUEST_URI'],
            'statusCode' => Craft::$app->response->getStatusCode(),
            'requestHeaders' => $requestHeaders,
            'responseHeaders' => $responseHeaders,
            'session' => json_encode($_SESSION ?? null),
            'response' => $response,
            'route' => Craft::$app->requestedAction ? Craft::$app->requestedAction->getUniqueId() : Craft::$app->requestedRoute,
            'action' => $action,
            'actionParams' => json_encode(Craft::$app->requestedParams),
            'isAjax' => Craft::$app->request->getIsAjax(),
            'isPjax' => Craft::$app->request->getIsPjax(),
            'isFlash' => Craft::$app->request->getIsFlash(),
            'isSecureConnection' => Craft::$app->request->getIsSecureConnection(),
            'startTime' => YII_BEGIN_TIME,
            'endTime' => microtime(true),
            'memory' => memory_get_peak_usage(),
            'querystring' => json_encode($querystring),
            'params' => Craft::$app->request->getBodyParams()
        ];

        $data['hash'] = sha1("{$data['method']} {$data['path']} {$data['statusCode']}");

        $data['duration'] = ($data['endTime'] - $data['startTime']) * 1000;

        foreach ($data['params'] as $key => $value) {
            $data['params'][$key] = $this->redact($key, $value);
        }

        foreach ($data['requestHeaders'] as $key => $value) {
            $data['requestHeaders'][$key] = $this->redact($key, $value);
        }

        foreach ($data['responseHeaders'] as $key => $value) {
            $data['responseHeaders'][$key] = $this->redact($key, $value);
        }

        $data['requestHeaders'] = json_encode($data['requestHeaders']);
        $data['responseHeaders'] = json_encode($data['responseHeaders']);
        $data['params'] = json_encode($data['params']);

        $this->addItem($data);
        return $data;
    }

    private function redact($key, $value)
    {
        $redacted = Craft::$app->security->redactIfSensitive($key, $value);
        return str_replace('\\u2022', '*', $redacted);
    }

    private function getLevelName($level)
    {
        $name = Logger::getLevelName($level);
        if ($name === 'warning') {
            $name = 'warn';
        }
        return $name;
    }
}
