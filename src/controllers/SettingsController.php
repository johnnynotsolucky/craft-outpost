<?php
namespace johnnynotsolucky\outpost\controllers;

use Craft;
use craft\web\Controller;
use craft\db\Query;
use yii\log\Logger;
use yii\base\Event;
use johnnynotsolucky\outpost\Plugin;
use johnnynotsolucky\outpost\models\Settings;
use johnnynotsolucky\outpost\events\RegisterStorageClassEvent;
use johnnynotsolucky\outpost\storage\DbStorage;

class SettingsController extends Controller
{
    const REGISTER_STORAGE_CLASS = 'registerStorageClass';

    public function actionIndex()
    {
        $this->requirePermission('configureOutpost');

        return $this->renderSettings();
    }

    public function actionSave()
    {
        $this->requirePermission('configureOutpost');

        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getBodyParam('settings');
        $settings = new Settings($postData);

        if (!$settings->validate() || !Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('outpost', 'Couldn’t save settings.'));
            return $this->renderSettings($settings);
        }

        Craft::$app->getSession()->setNotice(Craft::t('outpost', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionPurge()
    {
        $this->requirePermission('purgeOutpostData');

        Plugin::getInstance()->purge->all();
    }

    private function renderSettings($settings = null)
    {
        if (!$settings) {
            $settings = Plugin::getInstance()->settings;
        }

        return $this->renderTemplate(
            'outpost/settings/index',
            [
                'settings' => $settings,
                'logLevelOptions' => [
                    [
                        'label' => Craft::t('outpost', 'Error'),
                        'value' => Logger::LEVEL_ERROR,
                    ],
                    [
                        'label' => Craft::t('outpost', 'Warning'),
                        'value' => Logger::LEVEL_WARNING,
                    ],
                    [
                        'label' => Craft::t('outpost', 'Info'),
                        'value' => Logger::LEVEL_INFO,
                    ],
                    [
                        'label' => Craft::t('outpost', 'Trace'),
                        'value' => Logger::LEVEL_TRACE,
                    ]
                ],
            ]
        );
    }
}