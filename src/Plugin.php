<?php
namespace johnnynotsolucky\outpost;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\ExceptionEvent;
use craft\services\Utilities;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\web\UrlManager;
use craft\web\Application;
use craft\web\ErrorHandler;
use yii\base\Application as YiiApplication;
use yii\base\Event;
use yii\base\ActionEvent;
use yii\base\Controller;
use johnnynotsolucky\outpost\utilities\Utility;
use johnnynotsolucky\outpost\models\Settings;
use johnnynotsolucky\outpost\targets\StorageTarget;

class Plugin extends BasePlugin
{
    const TYPE_REQUEST = 'request';
    const TYPE_EXCEPTION = 'exception';
    const TYPE_PROFILE = 'profile';
    const TYPE_EVENT = 'event';
    const TYPE_LOG = 'log';

    const TABLES = [
        self::TYPE_REQUEST => [
            'table' => '{{%outpost_requests}}',
            'columns' => [
                'type', 'requestId', 'timestamp', 'hostname', 'method', 'path', 'statusCode',
                'requestHeaders', 'responseHeaders', 'session', 'response', 'route', 'action',
                'actionParams', 'isAjax', 'isPjax', 'isFlash', 'isSecureConnection',
                'startTime', 'endTime', 'memory', 'querystring', 'params', 'hash', 'duration'
            ]
        ],
        self::TYPE_EXCEPTION => [
            'table' => '{{%outpost_exceptions}}',
            'columns' => [
                'type', 'requestId', 'timestamp', 'class', 'shortClass', 'classHash', 'message', 'code', 'file',
                'line', 'simpleTrace', 'trace'
            ]
        ],
        self::TYPE_LOG => [
            'table' => '{{%outpost_logs}}',
            'columns' => ['type', 'requestId', 'timestamp', 'message', 'level', 'category']
        ],
        self::TYPE_EVENT => [
            'table' => '{{%outpost_events}}',
            'columns' => [
                'type', 'requestId', 'timestamp', 'eventName', 'eventClass', 'isStatic',
                'senderClass', 'senderData', 'data'
            ]
        ],
        self::TYPE_PROFILE => [
            'table' => '{{%outpost_profiles}}',
            'columns' => ['type', 'requestId', 'timestamp', 'duration', 'category', 'info', 'level', 'seq']
        ]
    ];

    private $storageTarget;

    public $hasCpSection = true;

    static function getRequestId()
    {
        if (isset($_SERVER['REQUEST_ID'])) {
            return $_SERVER['REQUEST_ID'];
        }

        $requestId = bin2hex(random_bytes(12));
        $_SERVER['REQUEST_ID'] = $requestId;
        return $requestId;
    }

    public function init()
    {
        parent::init();

        $this->setComponents([
            'purge' => \johnnynotsolucky\outpost\services\Purge::class,
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['outpost/settings'] = 'outpost/settings/index';
                $event->rules['outpost/settings/save'] = 'outpost/settings/save';
                $event->rules['outpost/grouped/requests'] = 'outpost/items/grouped-requests';
                $event->rules['outpost/grouped/requests/<hash>'] = 'outpost/items/grouped-requests';
                $event->rules['outpost/requests'] = 'outpost/items/requests';
                $event->rules['outpost/requests/<id>'] = 'outpost/items/requests';
                $event->rules['outpost/requests/<id>/<tab>'] = 'outpost/items/requests';
                $event->rules['outpost/requests/<requestId>/logs/<logId>'] = 'outpost/items/log';
                $event->rules['outpost/requests/<requestId>/exceptions/<exceptionId>'] = 'outpost/items/exception';
                $event->rules['outpost/requests/<requestId>/events/<eventId>'] = 'outpost/items/event';
                $event->rules['outpost/requests/<requestId>/timings/<timingId>'] = 'outpost/items/timing';
                $event->rules['outpost/grouped/exceptions'] = 'outpost/items/grouped-exceptions';
                $event->rules['outpost/grouped/exceptions/<classHash>'] = 'outpost/items/grouped-exceptions';
                $event->rules['outpost/exceptions'] = 'outpost/items/exceptions';
                $event->rules['outpost/logs'] = 'outpost/items/logs';
            }
        );

        $isCpRequest = Craft::$app->request->getIsCpRequest();
        if (!Craft::$app->request->getIsConsoleRequest()) {
            $settings = $this->getSettings();

            if (($isCpRequest && $settings->includeCpRequests) || !$isCpRequest) {
                $path = $_SERVER['REQUEST_URI'];
                $matchedPaths = [];

                $adminPrefix = Craft::$app->config->general->cpTrigger;

                if (
                    !preg_match("({$adminPrefix}\/outpost)", $path, $matchedPaths) &&
                    !preg_match("({$adminPrefix}\/actions\/debug)", $path, $matchedPaths)
                ) {
                    $this->storageTarget = new StorageTarget();
                    $targets = Craft::$app->log->targets;
                    Craft::$app->log->targets[] = $this->storageTarget;

                    Event::on('*', '*', function ($event) {
                        $sender = null;
                        if (property_exists($event, 'sender')) {
                            $sender = json_encode($event->sender);
                            $sender = $sender ?: null;
                        }

                        $this->storageTarget->addItem([
                            'type' => self::TYPE_EVENT,
                            'eventName' => $event->name,
                            'eventClass' => '\\'.get_class($event),
                            'isStatic' => !is_object($event->sender),
                            'senderClass' => '\\'.(is_object($event->sender)
                                ? get_class($event->sender)
                                : $event->sender),
                            'senderData' => $sender,
                            'data' => json_encode($event->data),
                        ]);
                    });

                    Event::on(
                        ErrorHandler::class,
                        ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
                        function(ExceptionEvent $event) {
                            $exception = new FlattenException($event->exception);

                            $exceptionClass = $exception->getClass();
                            $parts = explode('\\', $exceptionClass);
                            $shortClass = array_pop($parts);

                            $this->storageTarget->addItem([
                                'type' => self::TYPE_EXCEPTION,
                                'class' => $exceptionClass,
                                'shortClass' => $shortClass,
                                'classHash' => sha1($exceptionClass),
                                'message' => $exception->getMessage(),
                                'code' => $exception->getCode(),
                                'file' => $exception->getFile(),
                                'line' => $exception->getLine(),
                                'simpleTrace' => $exception->getTraceAsString(),
                                'trace' => json_encode($exception->getTrace()),
                            ]);
                        }
                    );
                }
            }
        }
    }

    public function getCpNavItem()
    {
        $item = parent::getCpNavItem();

        $settings = $this->getSettings();
        $groupedUrl = $settings->grouped ? 'outpost/grouped' : 'outpost';

        $item['label'] = Craft::t('outpost', 'Outpost');
        $item['badgeCount'] = 0;
        $item['subnav'] = [
            'requests' => ['label' => Craft::t('outpost', 'Requests'), 'url' => "{$groupedUrl}/requests"],
            'exceptions' => ['label' => Craft::t('outpost', 'Exceptions'), 'url' => "{$groupedUrl}/exceptions"],
            'logs' => ['label' => Craft::t('outpost', 'Logs'), 'url' => 'outpost/logs'],
            'settings' => ['label' => Craft::t('outpost', 'Settings'), 'url' => 'outpost/settings'],
        ];
        return $item;
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    public function settingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('outpost/settings', [
            'settings' => $this->getSettings(),
            'targets' => [],
        ]);
    }
}
