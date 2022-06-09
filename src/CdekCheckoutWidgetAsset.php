<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\cdek;

use skeeks\cms\base\AssetBundle;
use yii\web\JqueryAsset;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CdekCheckoutWidgetAsset extends AssetBundle
{
    public $sourcePath = '@skeeks/cms/shop/cdek/pvzwidget/widget';

    public $jsOptions = [
        'id' => 'ISDEKscript'
    ];

    public $css = [

    ];
    public $js = [
        'widjet.js',
    ];
    public $depends = [
        JqueryAsset::class,
    ];
}