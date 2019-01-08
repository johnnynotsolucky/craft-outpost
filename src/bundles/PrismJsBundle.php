<?php
namespace johnnynotsolucky\outpost\bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\prismjs\PrismJsAsset;

class PrismJsBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/../resources/';

        $this->depends = [CpAsset::class, PrismJsAsset::class];

        $this->js = [
            // 'js/prism-php.min.js',
            // 'js/prism-twig.min.js',
            'js/prism-sql.min.js',
            'js/prism-line-numbers.min.js',
            'js/prism-line-highlight.min.js',
        ];
        $this->css = [
            'css/prism-tomorrow.css',
            'css/prism-line-highlight.css',
            'css/prism-line-numbers.css',
        ];

        parent::init();
    }
}
