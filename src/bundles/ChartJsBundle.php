<?php
namespace johnnynotsolucky\outpost\bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ChartJsBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/../resources/';

        $this->depends = [CpAsset::class];

        $this->js = [
            'js/Chart.bundle.min.js',
            'js/Chart.min.js',
        ];

        parent::init();
    }
}
