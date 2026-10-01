<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\cdek;

use skeeks\cms\shop\delivery\DeliveryHandler;
use skeeks\yii2\form\fields\BoolField;
use skeeks\yii2\form\fields\FieldSet;
use skeeks\yii2\form\fields\TextField;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CdekDeliveryHandler extends DeliveryHandler
{

    /**
     * @var string Какой город отображается по умолчанию
     */
    public $defaultCity = '';

    public $defaultLatitude = '';
    public $defaultLongitude = '';

    /** Approximate city centres, used only for the initial map camera. */
    public static function getMapCityPresets()
    {
        return [
            'Москва' => [55.7558, 37.6176],
            'Санкт-Петербург' => [59.9391, 30.3159],
            'Рязань' => [54.6292, 39.7364],
            'Калуга' => [54.5138, 36.2612],
            'Нижний Новгород' => [56.3268, 44.0060],
            'Казань' => [55.7961, 49.1064],
            'Екатеринбург' => [56.8380, 60.5975],
            'Новосибирск' => [55.0302, 82.9204],
            'Самара' => [53.1955, 50.1002],
            'Краснодар' => [45.0355, 38.9747],
            'Ростов-на-Дону' => [47.2221, 39.7204],
            'Воронеж' => [51.6608, 39.2003],
        ];
    }

    /** Yandex Maps/widget v4 use [longitude, latitude], unlike the admin fields. */
    public function getWidgetDefaultLocation()
    {
        $latitude = str_replace(',', '.', trim((string)$this->defaultLatitude));
        $longitude = str_replace(',', '.', trim((string)$this->defaultLongitude));
        if (is_numeric($latitude) && is_numeric($longitude)
            && abs((float)$latitude) <= 90 && abs((float)$longitude) <= 180) {
            return [(float)$longitude, (float)$latitude];
        }

        $city = trim((string)$this->defaultCity) ?: 'Москва';
        foreach (self::getMapCityPresets() as $name => $coordinates) {
            if (mb_strtolower($city) === mb_strtolower($name)) {
                return [$coordinates[1], $coordinates[0]];
            }
        }
        return $city;
    }

    protected function getMapCityPresetsHint()
    {
        $buttons = [];
        foreach (self::getMapCityPresets() as $city => $coordinates) {
            $buttons[] = Html::button(Html::encode($city), [
                'type' => 'button',
                'class' => 'sx-button sx-button--secondary sx-button--sm',
                'data-sx-cdek-city' => $city,
                'data-sx-cdek-latitude' => $coordinates[0],
                'data-sx-cdek-longitude' => $coordinates[1],
                'onclick' => "var f=this.closest('form'); if(!f)return; var b=this; ['city','latitude','longitude'].forEach(function(k){var i=f.querySelector('input[data-sx-cdek-'+k+']'); if(i){i.value=b.getAttribute('data-sx-cdek-'+k); i.dispatchEvent(new Event('change',{bubbles:true}));}});",
            ]);
        }
        return Html::tag('div', 'Быстро заполнить город и координаты: ' . implode(' ', $buttons), [
            'class' => 'sx-cdek-city-presets',
        ]);
    }

    /**
     * @var string Из какого города будет идти доставка
     */
    public $cityFrom = 'Москва';


    /**
     * @var string
     */
    public $account = '';

    /**
     * @var string
     */
    public $secure = '';

    /**
     * @var string Можно выбрать страну, для которой отображать список ПВЗ
     */
    public $country = 'Россия';
    /**
     * @var int Legacy setting retained for loading existing configurations.
     * @deprecated Use isChooseTariff instead.
     */
    public $isCalculatePrice = 0;
    /**
     * @var int Выбирать тарифы при выборе пункта.
     */
    public $isChooseTariff = 0;
    /**
     * @var string Рассчитывать цену по выбранному ПВЗ?
     */
    public $isRequiredSelectPoint = 1;

    /**
     * @var string
     */
    public $checkoutModelClass = CdekCheckoutModel::class;
    public $checkoutWidgetClass = CdekCheckoutWidget::class;

    public function createService()
    {
        return new CdekService($this->account, $this->secure);
    }

    /** Widget and server recalculation must use the same parcel data (grams and cm). */
    public function getOrderPackages($order)
    {
        $packages = [];
        foreach ($order->shopOrderItems as $item) {
            $product = $item->shopProduct;
            for ($i = 0; $i < (int)ceil((float)$item->quantity); $i++) {
                $packages[] = [
                    'weight' => max(1, (int)($product && $product->weight ? $product->weight : 2)),
                    'length' => $product && $product->length ? max(1, (int)round($product->length / 10)) : 20,
                    'width' => $product && $product->width ? max(1, (int)round($product->width / 10)) : 20,
                    'height' => $product && $product->height ? max(1, (int)round($product->height / 10)) : 20,
                ];
            }
        }
        return $packages;
    }

    /**
     * @return array
     */
    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name' => \Yii::t('skeeks/shop/app', 'СДЭК'),
        ]);
    }


    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['defaultCity'], 'string'],
            [['defaultLatitude', 'defaultLongitude'], 'filter', 'filter' => static function ($value) {
                return str_replace(',', '.', trim((string)$value));
            }],
            [['defaultLatitude'], 'number', 'min' => -90, 'max' => 90],
            [['defaultLongitude'], 'number', 'min' => -180, 'max' => 180],
            [['defaultLatitude'], 'required', 'when' => function ($model) {
                return $model->defaultLongitude !== '' && $model->defaultLongitude !== null;
            }, 'whenClient' => "function(attribute,value){return $(attribute.input).closest('form').find('input[data-sx-cdek-longitude]').val() !== ''; }"],
            [['defaultLongitude'], 'required', 'when' => function ($model) {
                return $model->defaultLatitude !== '' && $model->defaultLatitude !== null;
            }, 'whenClient' => "function(attribute,value){return $(attribute.input).closest('form').find('input[data-sx-cdek-latitude]').val() !== ''; }"],
            [['cityFrom'], 'string'],

            [['account'], 'required'],
            [['secure'], 'required'],

            [['account'], 'string'],
            [['secure'], 'string'],
            [['country'], 'string'],
            [['isCalculatePrice'], 'integer'],
            [['isChooseTariff'], 'in', 'range' => [0, 1]],
            [['isRequiredSelectPoint'], 'integer'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'defaultCity'      => "Какой город отображается по умолчанию",
            'defaultLatitude' => 'Широта центра карты',
            'defaultLongitude' => 'Долгота центра карты',
            'cityFrom'         => "Из какого города будет идти доставка",
            'country'          => "Можно выбрать страну, для которой отображать список ПВЗ",
            'isCalculatePrice' => "Рассчитывать цену по выбранному ПВЗ?",
            'isChooseTariff' => "Выбирать тарифы при выборе пункта",
            'isRequiredSelectPoint' => "Для оформления заказа ПВЗ должен быть выбран обязательно?",

            'account' => "Account/Идентификатор",
            'secure' => "Secure password/Пароль",

            /*'api_key'     => "Ключ api",

            'custom_city' => "Город",
            'weight' => "Вес заказа",

            'height' => "Высота коробки заказа",
            'width'  => "Ширина коробки заказа",
            'depth'  => "Глубина коробки заказа",*/
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'defaultCity' => "Город, который покупатель увидит при открытии карты. Для городов из готового списка координаты уже сохранены. Если город не указан, используется Москва.",
            'defaultLatitude' => "Необязательно. Заполните широту и долготу, чтобы карта сразу открывалась в нужном месте без преобразования названия города через геокодер. Координаты имеют приоритет над городом. Можно выбрать готовый город ниже или указать свою точку; дробную часть отделяйте точкой или запятой.",
            'defaultLongitude' => "Координаты задают только начальный центр карты — покупатель сможет перемещать её и выбирать пункты в других городах. Ключ Яндекс.Карт всё равно нужен для самой карты; поиск адресов текстом требует геокодер. Оставьте оба поля пустыми, чтобы использовать город.",
            'isCalculatePrice' => "Если выбрано нет, то цена за доставку не будет рассчитываться.",
            'isChooseTariff' => "Нет — покупатель выбирает только пункт выдачи, стоимость берётся из поля «Цена» этого способа доставки. Да — покупатель также выбирает тариф СДЭК, и его стоимость добавляется к заказу.",
            'isRequiredSelectPoint' => "Если выбрано да - то без выбранного ПВЗ заказ оформить не получится. Если выбрано нет - то заказ можно оформить без выбора ПВЗ",

            'account' => "Получить доступ по адресу: <a href='https://lk.cdek.ru/integration'>https://lk.cdek.ru/integration</a>",
            'secure' => "Получить доступ по адресу: <a href='https://lk.cdek.ru/integration'>https://lk.cdek.ru/integration</a>",
        ]);
    }


    /**
     * @return array
     */
    public function getConfigFormFields()
    {
        return [
            'main' => [
                'class'  => FieldSet::class,
                'name'   => 'Основные',
                'fields' => [
                    'account',
                    'secure',

                    'defaultCity' => [
                        'class' => TextField::class,
                        'elementOptions' => ['data-sx-cdek-city' => true],
                    ],
                    'defaultLatitude' => [
                        'class' => TextField::class,
                        'elementOptions' => ['data-sx-cdek-latitude' => true, 'inputmode' => 'decimal'],
                    ],
                    'defaultLongitude' => [
                        'class' => TextField::class,
                        'elementOptions' => ['data-sx-cdek-longitude' => true, 'inputmode' => 'decimal'],
                        'hint' => $this->attributeHints()['defaultLongitude'] . $this->getMapCityPresetsHint(),
                    ],
                    'cityFrom',
                    'country',
                    'isChooseTariff' => [
                        'class' => BoolField::class
                    ],
                    'isRequiredSelectPoint' => [
                        'class' => BoolField::class
                    ],
                ],
            ],
            /*'default' => [
                'class'  => FieldSet::class,
                'name'   => 'Данные по умолчанию',
                'fields' => [
                    'custom_city',

                    'weight' => [
                        'class' => WidgetField::class,
                        'widgetClass' => SmartWeightInputWidget::class
                    ],

                    'height' => [
                        'class' => NumberField::class,
                        'append' => 'см.'
                    ],

                    'width' => [
                        'class' => NumberField::class,
                        'append' => 'см.'
                    ],

                    'depth' => [
                        'class' => NumberField::class,
                        'append' => 'см.'
                    ]
                ],
            ],*/
        ];
    }
}
