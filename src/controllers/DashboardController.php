<?php
namespace johnnynotsolucky\outpost\controllers;

use Craft;
use craft\web\Controller;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use johnnynotsolucky\outpost\Plugin;
use johnnynotsolucky\outpost\models\Request;
use johnnynotsolucky\outpost\models\Log;
use johnnynotsolucky\outpost\models\Exception;
use johnnynotsolucky\outpost\models\Profile;
use johnnynotsolucky\outpost\models\Event;
use yii\web\HttpException;
use yii\data\Pagination;

class DashboardController extends Controller
{
    public function actionIndex()
    {
        $spans = [
            '1hr' => [3600, 60],
            '6hr' => [21600, 360],
            '24hr' => [86400, 1440],
        ];

        $selectedSpan = Craft::$app->request->getParam('span');
        if (!$selectedSpan) {
            $selectedSpan = '1hr';
        }

        if (!isset($spans[$selectedSpan])) {
            $totalSpan = $spans['1hr'][0];
            $interval = $spans['61hr'][1];
        } else {
            $totalSpan = $spans[$selectedSpan][0];
            $interval = $spans[$selectedSpan][1];
        }

        $includeCpRequests = Craft::$app->request->getParam('cpRequests');

        if ($includeCpRequests === "1") {
            $includeCpRequests = true;
        } else {
            $includeCpRequests = false;
        }

        return $this->renderTemplate(
            'outpost/dashboard',
            [
                'requestsByStatus' => $this->requestsByStatusCode($totalSpan, $interval, $includeCpRequests),
                'busiestRequests' => $this->busiestRequests($totalSpan, $interval, $includeCpRequests),
                'slowestRequests' => $this->slowestRequests($totalSpan, $interval, $includeCpRequests),
                'averageDuration' => $this->averageDuration($totalSpan, $interval, $includeCpRequests),
                'selectedSpan' => $selectedSpan,
                'includeCpRequests' => $includeCpRequests,
            ]
        );
    }

    private function getStartTime($totalSpan, $interval)
    {
        return (int) ((time() - $totalSpan) / $interval) * $interval;
    }

    private function formatTimestamp($timestamp, $interval)
    {
        return (int) ($timestamp / $interval) * $interval;
    }

    private function getTimeSlots($totalSpan, $interval)
    {
        $startTime = $this->getStartTime($totalSpan, $interval);

        $slots = array_map(
            function ($slot) use ($interval) {
                return $this->formatTimestamp($slot, $interval);
            },
            range($startTime, $startTime + $totalSpan, $interval)
        );

        return [$startTime, $slots];
    }

    private function filterCpRequests($includeCpRequests, $query)
    {
        if (!$includeCpRequests) {
            return $query->andWhere(['isCpRequest' => false]);
        }

        return $query;
    }

    private function averageDuration($totalSpan, $interval, $includeCpRequests)
    {
        list($startTime, $slots) = $this->getTimeSlots($totalSpan, $interval);

        $data = $this->filterCpRequests(
            $includeCpRequests,
            (new Query())
                ->select(['timestamp', 'duration'])
                ->from('{{%outpost_requests}}')
                ->where(['>=', 'timestamp', $startTime])
                ->andWhere(['<', 'timestamp', $startTime + $totalSpan + $interval])
        )->all();


        $data = array_map(function ($item) use ($interval) {
            return [
                'group' => $this->formatTimestamp($item['timestamp'], $interval),
                'duration' => (int) $item['duration'],
            ];
        }, $data);

        $data = ArrayHelper::index($data, null, ['group']);

        $labels = $slots;
        $dataset = [];

        // Initialize dataset keys
        foreach ($slots as $slot) {
            $value = 0;
            if (isset($data[$slot])) {
                if (isset($data[$slot])) {
                    $requests = $data[$slot];
                    $sum = array_reduce($requests, function ($c, $r) {
                        return $c + $r['duration'];
                    }, 0);

                    $value = (int) ($sum / sizeof($requests));
                }
            }

            $datasets[] = $value;
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function slowestRequests($totalSpan, $interval, $includeCpRequests)
    {
        list($startTime, $slots) = $this->getTimeSlots($totalSpan, $interval);

        $data = $this->filterCpRequests(
            $includeCpRequests,
            (new Query())
                ->select(['timestamp', 'method', 'path', 'duration'])
                ->from('{{%outpost_requests}}')
                ->where(['>=', 'timestamp', $startTime])
                ->andWhere(['<', 'timestamp', $startTime + $totalSpan + $interval])
        )->all();

        $data = array_map(function ($item) use ($interval) {
            return [
                'group' => $this->formatTimestamp($item['timestamp'], $interval),
                'path' => "{$item['method']} {$item['path']}",
                'duration' => $item['duration'],
            ];
        }, $data);

        $uniquePaths = ArrayHelper::index($data, null, ['path']);

        $data = ArrayHelper::index($data, null, ['group', 'path']);

        $labels = $slots;
        $datasets = [];

        // Initialize dataset keys
        foreach ($uniquePaths as $path => $requests) {
            $datasets[$path] = [];
        }

        foreach ($slots as $slot) {
            foreach ($uniquePaths as $path => $requests) {
                $value = null;
                if (isset($data[$slot])) {
                    if (isset($data[$slot][$path])) {
                        $requests = $data[$slot][$path];
                        $requests = array_map(function ($r) {
                            return (int) $r['duration'];
                        }, $requests);
                        sort($requests);

                        $requestCount = sizeof($requests);
                        $percentileIndex = $requestCount * 0.95;
                        if (ceil($percentileIndex) > $percentileIndex) {
                            $value = $requests[ceil($percentileIndex) - 1];
                        } else {
                            $currentValue = $requests[$percentileIndex - 1];
                            $nextValue = $requests[$percentileIndex];

                            $value = (int) ($currentValue + $nextValue) / 2;
                        }
                    }
                }

                $datasets[$path][] = $value;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function busiestRequests($totalSpan, $interval, $includeCpRequests)
    {
        list($startTime, $slots) = $this->getTimeSlots($totalSpan, $interval);

        $data = $this->filterCpRequests(
            $includeCpRequests,
            (new Query())
            ->select(['timestamp', 'path', 'sampleSize'])
            ->from('{{%outpost_requests}}')
            ->where(['>=', 'timestamp', $startTime])
            ->andWhere(['<', 'timestamp', $startTime + $totalSpan + $interval])
        )->all();

        $data = array_map(function ($item) use ($interval) {
            return [
                'group' => $this->formatTimestamp($item['timestamp'], $interval), // date('H:i T', (string) ((int) ($item['timestamp'] / $interval) * $interval)),
                'path' => $item['path'],
                'sampleSize' => $item['sampleSize'],
            ];
        }, $data);

        $uniquePaths = ArrayHelper::index($data, null, ['path']);

        $data = ArrayHelper::index($data, null, ['group', 'path']);

        $labels = $slots;
        $datasets = [];

        // Initialize dataset keys
        foreach ($uniquePaths as $path => $requests) {
            $datasets[$path] = [];
        }

        // Populate counts
        foreach ($slots as $slot) {
            foreach ($uniquePaths as $path => $requests) {
                $currentCount = null;
                if (isset($data[$slot])) {
                    if (isset($data[$slot][$path])) {
                        $currentCount = array_reduce(
                            $data[$slot][$path],
                            function($carry, $request) {
                                return $carry + $request['sampleSize'];
                            },
                            0
                        );
                    }
                }

                $datasets[$path][] = $currentCount;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function requestsByStatusCode($totalSpan, $interval, $includeCpRequests)
    {
        list($startTime, $slots) = $this->getTimeSlots($totalSpan, $interval);

        $data = $this->filterCpRequests(
            $includeCpRequests,
            (new Query())
            ->select(['timestamp', 'statusCode', 'sampleSize'])
            ->from('{{%outpost_requests}}')
            ->where(['>', 'timestamp', time() - $totalSpan])
        )->all();

        $data = array_map(function ($item) use ($interval) {
            return [
                'group' => ((int) ($item['timestamp'] / $interval) * $interval),
                'statusCode' => (int) ($item['statusCode'] / 100) . 'xx',
                'sampleSize' => $item['sampleSize'],
            ];
        }, $data);

        $data = ArrayHelper::index($data, null, ['group', 'statusCode']);

        $labels = $slots;
        $datasets = [
            '1xx' => [],
            '2xx' => [],
            '3xx' => [],
            '4xx' => [],
            '5xx' => [],
        ];

        foreach ($slots as $slot) {
            foreach ($datasets as $code => $dataset) {
                if (isset($data[$slot][$code])) {
                    $x = array_reduce($data[$slot][$code], function ($carry, $group) {
                        return $carry + (int) $group['sampleSize'];
                    }, 0);
                    $datasets[$code][] = $x;
                } else {
                    $datasets[$code][] = 0;
                }
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }
}