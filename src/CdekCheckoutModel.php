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
    public $cityCode;
    public $street;
    public $house;
    public $flat;
    public $entrance;
    public $floor;
    public $comment;

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [
                ['address'],
                'required',
                'message' => 'Выберите пункт выдачи заказа СДЭК.',
                'when'    => function () {

                    if ($this->deliveryHandler) {
                        return !$this->deliveryHandler->isCourier() && $this->deliveryHandler->isRequiredSelectPoint;
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
            [['cityCode'], 'integer', 'min' => 1],
            [['street', 'house', 'flat', 'entrance', 'floor', 'comment'], 'trim'],
            [['street', 'house', 'flat', 'entrance', 'floor'], 'string', 'max' => 255],
            [['comment'], 'string', 'max' => 1000],
            [['cityCode', 'street', 'house'], 'required', 'when' => function () {
                return $this->deliveryHandler && $this->deliveryHandler->isCourier();
            }],
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
            'cityCode' => 'Город из справочника СДЭК',
            'street' => 'Улица', 'house' => 'Дом / корпус', 'flat' => 'Квартира / офис',
            'entrance' => 'Подъезд', 'floor' => 'Этаж', 'comment' => 'Комментарий курьеру',
            'tariffCode' => 'Тариф доставки',
        ]);
    }

    /**
     * @return array
     */
    public function getVisibleAttributes()
    {
        $result = [];
        if ($this->deliveryHandler && $this->deliveryHandler->isCourier()) {
            foreach (['city', 'street', 'house', 'flat', 'entrance', 'floor', 'comment', 'tariffCode'] as $attribute) {
                if ($this->$attribute !== null && $this->$attribute !== '') {
                    $result[$attribute] = ['value' => $this->$attribute, 'label' => $this->getAttributeLabel($attribute)];
                }
            }
            return $result;
        }

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
            'recipientMode' => $this->deliveryHandler->recipientMode,
            'senderMode' => $this->deliveryHandler->senderMode,
            'destination' => $this->deliveryHandler->isCourier()
                ? [(int)$this->cityCode, trim((string)$this->street), trim((string)$this->house), trim((string)$this->flat)] : null,
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
        $courier = $this->deliveryHandler->isCourier();
        if ((!$courier && !$this->id) || ($courier && !$this->validate(['cityCode', 'street', 'house'])) || !$this->tariffCode) {
            $this->deliveryCalculationError = $courier
                ? 'Укажите город, улицу и дом, затем выберите тариф СДЭК.'
                : 'Выберите пункт выдачи и тариф СДЭК для расчёта доставки.';
            return false;
        }
        try {
            if (!$packages || $this->shopOrder->currency_code !== 'RUB') {
                throw new \RuntimeException('Unsupported calculation inputs');
            }
            $service = $this->deliveryHandler->createService();
            $tariffs = $this->getAvailableTariffs($service);
            foreach ($tariffs as $tariff) {
                if ((int)$tariff['tariff_code'] === (int)$this->tariffCode
                    && $this->deliveryHandler->allowsDeliveryMode($tariff['delivery_mode'])
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

    /** Same authoritative request for tariff options and the final quote. */
    public function getAvailableTariffs($service = null)
    {
        $service = $service ?: $this->deliveryHandler->createService();
        if ($this->deliveryHandler->isCourier()) {
            if (!$this->validate(['cityCode', 'street', 'house'])) {
                throw new \InvalidArgumentException('Укажите город, улицу и дом.');
            }
            $city = $service->getCity($this->cityCode);
            $this->city = $city['city'] . (!empty($city['region']) ? ', ' . $city['region'] : '');
            $this->address = trim($this->street) . ', ' . trim($this->house);
            $destination = ['code' => (int)$city['code'], 'address' => $this->address];
        } else {
            $point = $service->getPickupPoint((string)$this->id);
            $destination = ['code' => (int)$point['location']['city_code']];
        }
        $packages = $this->deliveryHandler->getOrderPackages($this->shopOrder);
        if (!$packages || $this->shopOrder->currency_code !== 'RUB') {
            throw new \RuntimeException('Unsupported calculation inputs');
        }
        $tariffs = $service->getTariffs([
            'currency' => 1, 'lang' => 'rus',
            'from_location' => ['address' => $this->deliveryHandler->cityFrom ?: 'Москва'],
            'to_location' => $destination, 'packages' => $packages,
        ]);
        return array_values(array_filter($tariffs, function ($tariff) {
            return isset($tariff['delivery_mode'], $tariff['tariff_code'], $tariff['delivery_sum'])
                && $this->deliveryHandler->allowsDeliveryMode($tariff['delivery_mode'])
                && is_numeric($tariff['delivery_sum']) && (float)$tariff['delivery_sum'] >= 0;
        }));
    }

    public function beforeValidate()
    {
        if (!parent::beforeValidate()) {
            return false;
        }
        // Fixed-price courier orders still need an authoritative destination city.
        if ($this->deliveryHandler && $this->deliveryHandler->isCourier()
            && !$this->supportsAutomaticCalculation() && filter_var($this->cityCode, FILTER_VALIDATE_INT)
            && (int)$this->cityCode > 0) {
            try {
                $city = $this->deliveryHandler->createService()->getCity($this->cityCode);
                $this->city = $city['city'] . (!empty($city['region']) ? ', ' . $city['region'] : '');
            } catch (\Throwable $e) {
                $this->addError('cityCode', 'Не удалось проверить город СДЭК. Выберите город повторно.');
                return false;
            }
        }
        return true;
    }

    public function modifyOrder(\skeeks\cms\shop\models\ShopOrder $order)
    {
        parent::modifyOrder($order);
        if ($this->deliveryHandler->isCourier()) {
            $order->delivery_address = $this->city . ', ' . trim($this->street) . ', ' . trim($this->house);
            $order->delivery_apartment_number = $this->flat;
            $order->delivery_entrance = $this->entrance;
            $order->delivery_floor = $this->floor;
            $order->delivery_comment = $this->comment;
        }
        return $this;
    }


}
