<?php
namespace altomatic\assetbundles\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class CpAssetBundle extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [CpAsset::class];
        $this->js = ['altomatic.js'];
        parent::init();
    }
}
