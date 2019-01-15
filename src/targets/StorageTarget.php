<?php
namespace johnnynotsolucky\outpost\targets;

use Craft;
use craft\db\Query;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;
use johnnynotsolucky\outpost\Plugin;
use johnnynotsolucky\outpost\models\Request;
use johnnynotsolucky\outpost\models\Log;
use johnnynotsolucky\outpost\models\Event;
use johnnynotsolucky\outpost\models\Profile;
use johnnynotsolucky\outpost\models\Exception;

class StorageTarget extends Target
{
    private $items = [
        Request::TYPE => [],
        Exception::TYPE => [],
        Log::TYPE => [],
        Event::TYPE => [],
        Profile::TYPE => []
    ];

    private $hasException = false;

    public $logVars = [];

    public function addStorageHandler($handler)
    {
        $this->storageHandlers[] = $handler;
    }

    public function addItem($item)
    {
        if ($item['type'] === Exception::TYPE) {
            $this->hasException = true;
        }

        $item['requestId'] = Plugin::getRequestId();
        if (!isset($item['timestamp'])) {
            $item['timestamp'] = time();
        }

        $type = $item['type'];
        unset($item['type']);

        $modelClass = Plugin::getTableModel($type);
        $model = new $modelClass($item);

        if ($type === Request::TYPE) {
            // Always have only a single item for the Request
            $this->items[Request::TYPE] = [$model];
        } else {
            $this->items[$type][] = $model;
        }
    }

    private function isInSample($request)
    {
        $settings = Plugin::getInstance()->getSettings();

        $result = false;

        if ($settings->requestSampling) {
            $hash = $request['hash'];
            $key = "request_{$hash}";
            $cache = Craft::$app->getCache();
            $cached = $cache->get($key);

            if (!$cached) {
                $cached = [
                    'start_time' => time(),
                    'sample_size' => 0,
                    'count' => 0,
                ];

                $cache->set($key, $cached);
            }

            $now = time();
            if ($now - $cached['start_time'] > $settings->samplePeriod) {
                $totalRequests = (int) ceil($cached['count'] * ($settings->sampleRate / 100));
                if ($totalRequests > 0) {
                    $cached['sample_size'] = (int) ceil($cached['count'] / $totalRequests);
                }
                $cached['count'] = 0;
                $cached['start_time'] = $now;
            }

            if ($cached['sample_size'] > 0) {
                if ($cached['count'] % $cached['sample_size'] === 0) {
                    $result = $cached['sample_size'];
                }
            }

            $cached['count'] += 1;
            $cache->set($key, $cached);
        }

        return $result;
    }

    public function export()
    {
        $settings = Plugin::getInstance()->getSettings();

        $requestData = $this->getRequestData();

        $requestData['sampleSize'] = $this->isInSample($requestData);

        $this->addItem($requestData);

        if ($requestData['sampleSize']) {
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
                            'type' => Log::TYPE,
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
                    'type' => Profile::TYPE,
                    'duration' => $timing['duration'] * 1000, // in milliseconds
                    'category' => $timing['category'],
                    'info' => $timing['info'],
                    'level' => $this->getLevelName($timing['level']),
                    'seq' => $seq,
                ]);
            }

            $instance = Plugin::getInstance()->getStorageInstance();

            foreach ($this->items as $key => $items) {
                $instance->store($key, $items);
                $this->items[$key] = [];
            }

            if ($settings->purgeLimit > 0) {
                Plugin::getInstance()->purge->old($settings->purgeLimit);
            }
        }
    }

    private function getRequestData()
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
            'type' => Request::TYPE,
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
