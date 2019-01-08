<?php
namespace johnnynotsolucky\outpost\controllers;

use Craft;
use craft\web\Controller;
use craft\db\Query;
use yii\log\Logger;
use johnnynotsolucky\outpost\Plugin;
use johnnynotsolucky\outpost\models\Settings;

class SettingsController extends Controller
{

    public function actionIndex()
    {
        return $this->renderSettings();
    }

    public function actionSave()
    {
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
                ]
            ]
        );
    }
}