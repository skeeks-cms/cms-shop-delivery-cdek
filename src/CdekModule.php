<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\cdek;

use skeeks\cms\money\Money;
use skeeks\cms\shop\delivery\DeliveryCheckoutModel;
use skeeks\cms\shop\models\ShopOrder;
use yii\base\Module;
use yii\helpers\ArrayHelper;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CdekModule extends Module
{
    public $controllerNamespace = 'skeeks\cms\shop\cdek\controllers';
}