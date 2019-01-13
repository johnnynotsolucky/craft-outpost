<?php
namespace johnnynotsolucky\outpost;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\ExceptionEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Utilities;
use craft\services\UserPermissions;
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
use johnnynotsolucky\outpost\models\Request;
use johnnynotsolucky\outpost\models\Log;
use johnnynotsolucky\outpost\models\Event as OutpostEvent;
use johnnynotsolucky\outpost\models\Profile;
use johnnynotsolucky\outpost\models\Exception;

class Plugin extends BasePlugin
{
    const TABLES = [
        Request::class,
        Log::class,
        OutpostEvent::class,
        Profile::class,
        Exception::class,
    ];

    private $storageTarget;

    private $storageInstance;

    public $hasCpSection = true;

    static function getTableModel($type)
    {
        foreach (self::TABLES as $model) {
            if ($model::TYPE === $type) {
                return $model;
            }
        }
    }

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
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions['Outpost'] = [
                    'viewOutpostData' => [
                        'label' => 'View request data',
                    ],
                    'configureOutpost' => [
                        'label' => 'Configuration',
                    ],
                    'purgeOutpostData' => [
                        'label' => 'Purge data',
                    ],
                ];
            }
        );

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
                            'type' => OutpostEvent::TYPE,
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
                                'type' => Exception::TYPE,
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

    public function getStorageInstance()
    {
        if (!$this->storageInstance) {
            $class = $this->getSettings()->storageClass;
            $this->storageInstance = new $class();
        }

        return $this->storageInstance;
    }

    public function getCpNavItem()
    {
        $item = parent::getCpNavItem();

        $settings = $this->getSettings();
        $groupedUrl = $settings->grouped ? 'outpost/grouped' : 'outpost';

        $item['label'] = Craft::t('outpost', 'Outpost');
        $item['badgeCount'] = 0;

        $subnav = [];
        if (Craft::$app->user->checkPermission('viewOutpostData')) {
            $subnav['requests'] = ['label' => Craft::t('outpost', 'Requests'), 'url' => "{$groupedUrl}/requests"];
            $subnav['exceptions'] = ['label' => Craft::t('outpost', 'Exceptions'), 'url' => "{$groupedUrl}/exceptions"];
            $subnav['logs'] = ['label' => Craft::t('outpost', 'Logs'), 'url' => 'outpost/logs'];
        }

        if (Craft::$app->user->checkPermission('configureOutpost')) {
            $subnav['settings'] = ['label' => Craft::t('outpost', 'Settings'), 'url' => 'outpost/settings'];
        }

        $item['subnav'] = $subnav;

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
