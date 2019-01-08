<?php
namespace johnnynotsolucky\outpost\bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\prismjs\PrismJsAsset;

class ResourceBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/../resources/';

        $this->depends = [CpAsset::class];

        $this->js = [];
        $this->css = ['css/main.css'];

        parent::init();
    }
}
