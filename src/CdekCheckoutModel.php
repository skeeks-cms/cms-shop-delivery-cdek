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

    public $dataAddress;
    public $dataPrice;

    public $name;
    public $city;
    public $address;
    public $worktime;
    public $phone;

    public $price;
    public $tariffCode;

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
            [['tariffCode'], 'integer', 'min' => 1],
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
        if ($this->supportsAutomaticCalculation()) {
            return new Money($this->deliveryCalculationError ? '0' : (string)($this->price ?: '0'), $this->shopOrder->currency_code);
        }
        return parent::getMoney();
    }

    public function supportsAutomaticCalculation()
    {
        return $this->deliveryHandler && (bool)$this->deliveryHandler->isChooseTariff;
    }

    public function refreshDeliveryPrice($force = false)
    {
        if (!$this->supportsAutomaticCalculation()) {
            return true;
        }
        $packages = $this->deliveryHandler->getOrderPackages($this->shopOrder);
        $input = [
            'point' => (string)$this->id,
            'tariff' => (int)$this->tariffCode,
            'origin' => $this->deliveryHandler->cityFrom ?: 'Москва',
            'currency' => $this->shopOrder->currency_code,
            'packages' => $packages,
            'account' => hash('sha256', $this->deliveryHandler->account . ':' . $this->deliveryHandler->secure),
        ];
        $hash = hash('sha256', json_encode($input));
        if (!$force && $this->deliveryCalculationHash === $hash
            && (int)$this->deliveryCalculatedAt > time() - 300) {
            return !$this->deliveryCalculationError;
        }
        $this->deliveryCalculationHash = $hash;
        $this->deliveryCalculatedAt = time();
        $this->deliveryCalculationError = null;
        $this->price = null;
        if (!$this->id || !$this->tariffCode) {
            $this->deliveryCalculationError = 'Выберите пункт выдачи и тариф СДЭК для расчёта доставки.';
            return false;
        }
        try {
            if (!$packages || $this->shopOrder->currency_code !== 'RUB') {
                throw new \RuntimeException('Unsupported calculation inputs');
            }
            $service = $this->deliveryHandler->createService();
            $point = $service->getPickupPoint((string)$this->id);
            $tariffs = $service->getTariffs([
                'currency' => 1,
                'lang' => 'rus',
                'from_location' => ['address' => $input['origin']],
                'to_location' => ['code' => (int)$point['location']['city_code']],
                'packages' => $packages,
            ]);
            foreach ($tariffs as $tariff) {
                if ((int)$tariff['tariff_code'] === (int)$this->tariffCode
                    && in_array((int)$tariff['delivery_mode'], [2, 4, 5, 6], true)
                    && isset($tariff['delivery_sum']) && is_numeric($tariff['delivery_sum'])
                    && (float)$tariff['delivery_sum'] >= 0) {
                    $this->price = (string)$tariff['delivery_sum'];
                    return true;
                }
            }
            $this->deliveryCalculationError = 'Выбранный тариф СДЭК недоступен для текущей корзины. Выберите другой тариф.';
        } catch (\Throwable $exception) {
            // Do not log requests, credentials or remote response bodies.
            $this->deliveryCalculationError = 'Не удалось рассчитать доставку СДЭК. Повторите попытку или выберите другой способ доставки.';
        }
        // Failed quotes are retried on the next request, never treated as a fresh zero quote.
        $this->deliveryCalculatedAt = null;
        return false;
    }


}
