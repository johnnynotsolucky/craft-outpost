<?php
namespace johnnynotsolucky\outpost\console\controllers;

use Craft;
use yii\console\Controller;
use johnnynotsolucky\outpost\Plugin;

class PurgeController extends Controller
{
    /**
     * @var integer|null Number of requests to keep in the database.
     */
    public $keep;

    public function options($actionID)
    {
        return ['keep'];
    }

    public function optionAliases()
    {
        return ['k' => 'keep'];
    }

    /**
     * Purge stored request data
     */
    public function actionIndex()
    {
        if ($this->keep > 0) {
            Plugin::getInstance()->purge->old($this->keep);
        } else {
            Plugin::getInstance()->purge->all();
        }
    }
}