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
use yii\helpers\ArrayHelper;

/**
 * @property CdekDeliveryHandler $deliveryHandler
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CdekCheckoutModel extends DeliveryCheckoutModel
{
    /**
     * @var string
     */
    public $id;

    public $name;
    public $city;
    public $address;
    public $worktime;
    public $phone;

    public $price;

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [
                ['address'],
                'required',
                'message' => 'Выберите пункт выдачи заказа СДЭК.',
                'when'    => function () {

                    if ($this->deliveryHandler) {
                        return $this->deliveryHandler->isRequiredSelectPoint;
                    }
                    return true;
            },
            ],
            [['address'], 'string'],
            [['city'], 'string'],
            [['name'], 'string'],
            [['address'], 'string'],
            [['price'], 'string'],
            [['id'], 'string'],
            [['worktime'], 'string'],
            [['phone'], 'string'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'name'     => "Название ПВЗ",
            'address'  => "Адрес ПВЗ",
            'price'    => "Цена",
            'id'       => "Код ПВЗ",
            'worktime' => "Время работы",
            'phone'    => "Телефон",
            'city'     => "Город",
        ]);
    }

    /**
     * @return array
     */
    public function getVisibleAttributes()
    {
        $result = [];

        if ($this->city) {
            $result['city'] = [
                'value' => $this->city,
                'label' => 'Город',
            ];
        }
        if ($this->address) {
            $result['address'] = [
                'value' => $this->address,
                'label' => 'Адрес',
            ];
        }
        if ($this->name) {
            $result['name'] = [
                'value' => $this->name,
                'label' => 'Название',
            ];
        }
        if ($this->phone) {
            $result['phone'] = [
                'value' => $this->phone,
                'label' => 'Телефон',
            ];
        }
        if ($this->worktime) {
            $result['worktime'] = [
                'value' => $this->worktime,
                'label' => 'Время работы',
            ];
        }

        if ($this->id) {
            $result['id'] = [
                'value' => $this->id,
                'label' => 'Код ПВЗ',
            ];
        }

        /*if ($this->money->amount) {
            $result['price'] = [
                'value' => (string)$this->money,
                'label' => 'Стоимость',
            ];
        }*/


        return $result;
    }

    /**
     * @return Money
     */
    public function getMoney()
    {
        if ((float)$this->price) {
            return new Money((string)$this->price, $this->shopOrder->currency_code);
        }
        return parent::getMoney();
    }


}