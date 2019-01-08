<?php
namespace johnnynotsolucky\outpost\controllers;

use Craft;
use craft\web\Controller;
use craft\db\Query;
use johnnynotsolucky\outpost\Plugin;
use johnnynotsolucky\outpost\models\Settings;
use yii\web\HttpException;
use yii\data\Pagination;

class ItemsController extends Controller
{
    public function actionRequests($id = null, $tab = 'details')
    {
        if ($id === null) {
            return $this->getAllRequests();
        } else {
            return $this->getRequest($id, $tab);
        }
    }

    public function actionGroupedRequests($hash = null)
    {
        if ($hash === null) {
            return $this->getGroupedRequests();
        } else {
            return $this->getRequestsByHash($hash);
        }
    }

    private function addExceptionParamsToQuery($query)
    {
        $request = Craft::$app->request;
        $selectedClassHash = $request->getParam('class');
        $searchQuery = $request->getParam('searchQuery');

        if ($selectedClassHash) {
            $query->andWhere(['classHash' => $selectedClassHash]);
        }

        if (trim($searchQuery)) {
            $query->andWhere(['LIKE', 'message', $searchQuery]);
        }

        return $query;
    }

    private function getExceptionQueryOptions()
    {
        $request = Craft::$app->request;
        $selectedClassHash = $request->getParam('class');
        $searchQuery = $request->getParam('searchQuery');

        $classNames = (new Query())
            ->select(['MAX(shortClass) as label', 'classHash as value'])
            ->from('{{%outpost_exceptions}}')
            ->groupBy(['classHash'])
            ->all();

        $classNames = array_merge(
            [['value' => '', 'label' => 'All classes']],
            $classNames
        );

        return [
            'classes' => $classNames,
            'selectedClassHash' => $selectedClassHash,
            'searchQuery' => $searchQuery,
        ];
    }

    public function actionExceptions($page = 1)
    {
        $query = (new Query())
            ->select(['*'])
            ->from('{{%outpost_exceptions}}')
            ->orderBy(['timestamp' => SORT_DESC]);

        $query = $this->addExceptionParamsToQuery($query);

        $items = $query->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $items);

        return $this->renderTemplate(
            'outpost/exceptions',
            array_merge(
                ['exceptions' => $mapped],
                $this->getExceptionQueryOptions()
            )
        );
    }

    public function actionGroupedExceptions($classHash = null)
    {
        if ($classHash === null) {
            return $this->getGroupedExceptions();
        } else {
            return $this->getExceptionsByHash($classHash);
        }
    }

    public function getPagination($query)
    {
        $page = Craft::$app->request->getParam('page');

        if (!$page) {
            $page = 1;
        }

        $page = $page - 1;
        if ($page < 0) {
            throw new HttpException(404);
        }

        $pages = new Pagination(['pageSize' => 10, 'page' => $page, 'totalCount' => (clone $query)->count()]);

        if ($page >= $pages->pageCount && $page != 0) {
            throw new HttpException(404);
        }

        return $pages;
    }

    public function actionLogs()
    {
        $request = Craft::$app->request;
        $selectedLevel = $request->getParam('level');
        $searchQuery = $request->getParam('searchQuery');

        $query = (new Query())
            ->select(['*'])
            ->from('{{%outpost_logs}}')
            ->orderBy(['timestamp' => SORT_DESC]);

        if ($selectedLevel) {
            $query = $query->andWhere(['level' => $selectedLevel]);
        }

        if (trim($searchQuery)) {
            $query->andWhere(['LIKE', 'message', $searchQuery]);
        }

        $pages = $this->getPagination($query);

        $items = $query
            ->offset($pages->offset)
            ->limit($pages->limit)
            ->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $items);

        return $this->renderTemplate(
            'outpost/logs',
            [
                'logs' => $mapped,
                'pages' => $pages->pageCount,
                'page' => $pages->page + 1,
                'levels' => [
                    ['value' => '', 'label' => 'All levels'],
                    ['value' => 'error', 'label' => 'Error'],
                    ['value' => 'warn', 'label' => 'Warning'],
                    ['value' => 'info', 'label' => 'Info'],
                    ['value' => 'trace', 'label' => 'Trace'],
                ],
                'selectedLevel' => $selectedLevel,
                'searchQuery' => $searchQuery,
            ]
        );
    }

    public function actionLog($requestId, $logId)
    {
        $log = (new Query())
            ->select(['*'])
            ->from('{{%outpost_logs}}')
            ->where(['requestId' => $requestId])
            ->andWhere(['id' => $logId])
            ->one();


        if ($log === null) {
            throw new HttpException(404);
        }

        $log['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $log['timestamp']));

        $log['isJson'] = false;
        if ($json = json_decode($log['message'], true)) {
            $log['isJson'] = true;
            $log['message'] = json_encode($json, JSON_PRETTY_PRINT);
        }

        return $this->renderTemplate(
            'outpost/request/log',
            [
                'log' => $log,
                'request' => $this->getRequestData($requestId),
            ]
        );
    }

    public function actionEvent($requestId, $eventId)
    {
        $event = (new Query())
            ->select(['*'])
            ->from('{{%outpost_events}}')
            ->where(['requestId' => $requestId])
            ->andWhere(['id' => $eventId])
            ->one();

        if ($event === null) {
            throw new HttpException(404);
        }

        $event['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $event['timestamp']));

        $event['senderData'] = json_encode(json_decode($event['senderData']), JSON_PRETTY_PRINT);
        $event['data'] = json_encode(json_decode($event['data']), JSON_PRETTY_PRINT);

        return $this->renderTemplate(
            'outpost/request/event',
            [
                'event' => $event,
                'request' => $this->getRequestData($requestId),
            ]
        );
    }

    public function actionTiming($requestId, $timingId)
    {
        $timing = (new Query())
            ->select(['*'])
            ->from('{{%outpost_profiles}}')
            ->where(['requestId' => $requestId])
            ->andWhere(['id' => $timingId])
            ->one();

        if ($timing === null) {
            throw new HttpException(404);
        }

        $timing['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $timing['timestamp']));

        return $this->renderTemplate(
            'outpost/request/timing',
            [
                'timing' => $timing,
                'request' => $this->getRequestData($requestId),
            ]
        );
    }

    public function actionException($requestId, $exceptionId)
    {
        $exception = (new Query())
            ->select(['*'])
            ->from('{{%outpost_exceptions}}')
            ->where(['requestId' => $requestId])
            ->andWhere(['id' => $exceptionId])
            ->one();


        if ($exception === null) {
            throw new HttpException(404);
        }

        $exception['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $exception['timestamp']));
        $exception['trace'] = json_decode($exception['trace'], true);

        $stacks = [
            $this->renderCallStackItem($exception['file'], $exception['line'], null, null, [], 1)
        ];

        foreach ($exception['trace'] as $index => $trace) {
            $stacks[] = $this->renderCallStackItem($trace['file'], $trace['line'], $trace['class'], $trace['function'], $trace['args'], $index + 2);
        }


        return $this->renderTemplate(
            'outpost/request/exception',
            [
                'exception' => $exception,
                'stacks' => $stacks,
                'request' => $this->getRequestData($requestId),
            ]
        );
    }

    private function renderCallStackItem($file, $line, $class, $method, $args, $index)
    {
        $maxSourceLines = 19;
        $maxTraceSourceLines = 13;

        $class = empty($class) ? null : $class;

        $lines = [];
        $begin = $end = 0;
        if ($file !== null && $line !== null) {
            $line--; // adjust line number from one-based to zero-based
            $lines = @file($file);
            if ($line < 0 || $lines === false || ($lineCount = count($lines)) < $line) {
                return '';
            }

            $half = (int) (($index === 1 ? $maxSourceLines : $maxTraceSourceLines) / 2);
            $begin = $line - $half > 0 ? $line - $half : 0;
            $end = $line + $half < $lineCount ? $line + $half : $lineCount - 1;
        }

        //

        // return $this->renderFile($this->callStackItemView, );
        $handler = Craft::$app->getErrorHandler();

        try {
            $typeLinks = $handler->addTypeLinks("{$class}::{$method}");
        } catch (\ReflectionException $e) {
            $typeLinks = null;
        }

        return Craft::$app->view->renderTemplate(
            'outpost/request/stackTrace',
            [
                'isCoreFile' => $handler->isCoreFile($file),
                'handler' => $handler,
                'file' => $file,
                'line' => $line,
                'class' => $class,
                'typeLinks' => $typeLinks,
                'method' => $method,
                'index' => $index,
                'lines' => $lines,
                'begin' => $begin,
                'end' => $end,
                'args' => $args,
            ]
        );
    }

    private function getGroupedRequests()
    {
        $groupQuery = (new Query())
            ->select(['MAX(id) as id', 'COUNT(*) as requestCount'])
            ->from('{{%outpost_requests}}')
            ->groupBy(['hash'])
            ->orderBy(['timestamp' => SORT_DESC]);

        $groupQuery = $this->addRequestParamsToQuery($groupQuery);

        $results = (new Query())
            ->select(['requestId', 'statusCode', 'requests.hash', 'path', 'method', 'timestamp', 'requestCount'])
            ->from(['r' => $groupQuery])
            ->leftJoin('{{%outpost_requests}} requests', '[[requests.id]] = [[r.id]]')
            ->orderBy(['timestamp' => SORT_DESC])
            ->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $results);

        return $this->renderTemplate(
            'outpost/groupedRequests',
            array_merge(
                ['requests' => $mapped],
                $this->getRequestQueryOptions()
            )
        );
    }

    private function getGroupedExceptions()
    {
        $groupQuery = (new Query())
            ->select(['MAX(id) as id', 'COUNT(*) as exceptionCount'])
            ->from('{{%outpost_exceptions}}')
            ->groupBy(['classHash']);

        $groupQuery = $this->addExceptionParamsToQuery($groupQuery);

        $results = (new Query())
            ->select(['classHash', 'e.id', 'requestId', 'class', 'message', 'timestamp', 'exceptionCount'])
            ->from(['e' => $groupQuery])
            ->leftJoin('{{%outpost_exceptions}} exceptions', '[[exceptions.id]] = [[e.id]]')
            ->orderBy(['timestamp' => SORT_DESC])
            ->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $results);

        return $this->renderTemplate(
            'outpost/groupedExceptions',
            array_merge(
                ['exceptions' => $mapped],
                $this->getExceptionQueryOptions()
            )
        );
    }

    private function addRequestParamsToQuery($query)
    {
        $request = Craft::$app->request;
        $selectedStatus = $request->getParam('status');
        $selectedMethod = $request->getParam('method');
        $searchQuery = $request->getParam('searchQuery');

        if ($selectedStatus) {
            $query->andWhere(['statusCode' => $selectedStatus]);
        }

        if ($selectedMethod) {
            $query->andWhere(['method' => $selectedMethod]);
        }

        if (trim($searchQuery)) {
            $query->andWhere(['LIKE', 'path', $searchQuery]);
        }

        return $query;
    }

    private function getRequestQueryOptions()
    {
        $request = Craft::$app->request;
        $selectedStatus = $request->getParam('status');
        $selectedMethod = $request->getParam('method');
        $searchQuery = $request->getParam('searchQuery');

        $statuses = (new Query())
            ->select(['statusCode as value', 'statusCode as label'])
            ->from('{{%outpost_requests}}')
            ->groupBy(['statusCode'])
            ->all();

        $statuses = array_merge(
            [['value' => '', 'label' => 'All statuses']],
            $statuses
        );

        $methods = (new Query())
            ->select(['method'])
            ->from('{{%outpost_requests}}')
            ->groupBy(['method'])
            ->all();

        $methods = array_map(function ($method) {
            return [
                'value' => $method['method'],
                'label' => strtoupper($method['method']),
            ];
        }, $methods);

        $methods = array_merge(
            [['value' => '', 'label' => 'All methods']],
            $methods
        );

        return [
            'statuses' => $statuses,
            'selectedStatus' => $selectedStatus,
            'methods' => $methods,
            'selectedMethod' => $selectedMethod,
            'searchQuery' => $searchQuery,
        ];
    }

    private function getAllRequests()
    {
        $query = (new Query())
            ->select(['*'])
            ->from('{{%outpost_requests}}')
            ->orderBy(['timestamp' => SORT_DESC]);

        $query = $this->addRequestParamsToQuery($query);

        $pages = $this->getPagination($query);

        $results = $query
            ->offset($pages->offset)
            ->limit($pages->limit)
            ->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $results);

        return $this->renderTemplate(
            'outpost/requests',
            array_merge(
                [
                    'requests' => $mapped,
                    'pages' => $pages->pageCount,
                    'page' => $pages->page + 1,
                ],
                $this->getRequestQueryOptions()
            )
        );
    }

    private function getRequestsByHash($hash)
    {
        $results = (new Query())
            ->select(['*'])
            ->from('{{%outpost_requests}}')
            ->where(['hash' => $hash])
            ->orderBy(['timestamp' => SORT_DESC])
            ->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $results);

        return $this->renderTemplate(
            'outpost/requests',
            [
                'requests' => $mapped,
                'byHash' => true
            ]
        );
    }

    private function getExceptionsByHash($classHash)
    {
        $results = (new Query())
            ->select(['*'])
            ->from('{{%outpost_exceptions}}')
            ->where(['classHash' => $classHash])
            ->orderBy(['timestamp' => SORT_DESC])
            ->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $results);

        return $this->renderTemplate(
            'outpost/exceptions',
            [
                'exceptions' => $mapped,
                'byHash' => true
            ]
        );
    }

    private function getRequest($id, $tab)
    {
        switch($tab) {
            case 'details':
                return $this->getRequestDetails($id);
            case 'exceptions':
                return $this->getRequestExceptions($id);
            case 'profiling':
                return $this->getRequestProfiling($id);
            case 'logs':
                return $this->getRequestLogs($id);
            case 'events':
                return $this->getRequestEvents($id);
            default:
                throw new HttpException(404);
        }
    }

    private function getRequestDetails($id)
    {
        $request = $this->getRequestData($id);

        return $this->renderTemplate(
            "outpost/request/details",
            ['request' => $request]
        );
    }

    private function getRequestData($id)
    {
        $results = $this->getItemsByRequest(Plugin::TYPE_REQUEST, $id);

        $request = null;
        if (sizeof($results) > 0) {
            $request = $results[0];
        } else {
            throw new HttpException(404);
        }

        $request['isJson'] = false;
        if ($json = json_decode($request['response'], true)) {
            $request['isJson'] = true;
            $request['response'] = json_encode($json, JSON_PRETTY_PRINT);
        }

        $request['duration'] = $request['duration'];
        $request['requestHeaders'] = json_encode(json_decode($request['requestHeaders']), JSON_PRETTY_PRINT);
        $request['requestHeaders'] = str_replace('\\u2022', '*', $request['requestHeaders']);
        $request['params'] = json_encode(json_decode($request['params']), JSON_PRETTY_PRINT);
        $request['params'] = str_replace('\\u2022', '*', $request['params']);
        $request['querystring'] = json_encode(json_decode($request['querystring']), JSON_PRETTY_PRINT);

        $request['responseHeaders'] = json_encode(json_decode($request['responseHeaders']), JSON_PRETTY_PRINT);

        return $request;
    }

    private function getRequestProfiling($id)
    {
        $request = $this->getRequestData($id);
        $timings = $this->getItemsByRequest(
            Plugin::TYPE_PROFILE,
            $id,
            ['duration' => SORT_DESC]
        );

        return $this->renderTemplate(
            "outpost/request/timings",
            [
                'request' => $request,
                'timings' => $timings,
            ]
        );
    }

    private function getRequestLogs($id)
    {
        $request = $this->getRequestData($id);
        $logs = $this->getItemsByRequest(Plugin::TYPE_LOG, $id);

        return $this->renderTemplate(
            "outpost/request/logs",
            [
                'request' => $request,
                'logs' => $logs,
            ]
        );
    }

    private function getRequestExceptions($id)
    {
        $request = $this->getRequestData($id);
        $exceptions = $this->getItemsByRequest(Plugin::TYPE_EXCEPTION, $id);

        return $this->renderTemplate(
            "outpost/request/exceptions",
            [
                'request' => $request,
                'exceptions' => $exceptions,
            ]
        );
    }

    private function getRequestEvents($id)
    {
        $request = $this->getRequestData($id);
        $events = $this->getItemsByRequest(Plugin::TYPE_EVENT, $id);

        return $this->renderTemplate(
            "outpost/request/events",
            [
                'request' => $request,
                'events' => $events,
            ]
        );
    }

    private function getItemsByRequest($type, $id, $orderBy = null)
    {
        $items = (new Query())
            ->select(['*'])
            ->from(Plugin::TABLES[$type]['table'])
            ->where(['requestId' => $id])
            ->orderBy($orderBy ?: ['timestamp' => SORT_DESC])
            ->all();

        $mapped = array_map(function ($item) {
            $item['timestamp'] = date('Y-m-d H:i:s T', (string) ((int) $item['timestamp']));
            return $item;
        }, $items);

        return $mapped;
    }
}