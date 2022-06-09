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
use yii\helpers\ArrayHelper;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CdekCheckoutModel extends DeliveryCheckoutModel
{
    /**
     * @var string
     */
    public $id;
    public $name;
    public $address;
    public $price;

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['address'], 'required', 'message' => 'Выберите пункт выдачи заказа СДЭК.'],
            [['address'], 'string'],
            [['name'], 'string'],
            [['address'], 'string'],
            [['price'], 'string'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'name'           => "Название ПВЗ",
            'address'         => "Адрес ПВЗ",
            'price'        => "Цена",
        ]);
    }

    /**
     * @return array
     */
    public function getVisibleAttributes()
    {
        return [
            'name',
            'address',
        ];
    }

    /**
     * @return Money
     */
    public function getMoney()
    {
        return new Money((string) $this->price, $this->shopOrder->currency_code);
    }



}