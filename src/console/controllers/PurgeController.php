<?php
namespace johnnynotsolucky\outpost\console\controllers;

use Craft;
use yii\console\Controller;
use johnnynotsolucky\outpost\Plugin;

class PurgeController extends Controller
{
    public $keep;

    public function options($actionID)
    {
        return ['keep'];
    }

    public function optionAliases()
    {
        return ['k' => 'keep'];
    }

    public function actionIndex()
    {
        if ($this->keep > 0) {
            Plugin::getInstance()->purge->old($this->keep);
        } else {
            Plugin::getInstance()->purge->all();
        }
    }
}