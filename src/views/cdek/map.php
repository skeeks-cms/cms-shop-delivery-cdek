<?php
/**
 * @var $this yii\web\View
 * @var $shopDelivery \skeeks\cms\shop\models\ShopDelivery
 * @var $shopOrder \skeeks\cms\shop\models\ShopOrder
 * @var $cdekHandler \skeeks\cms\shop\cdek\CdekDeliveryHandler
 */

$cdekHandler = $shopDelivery->handler;


$goods = [];
if ($shopOrder->shopOrderItems)
{
    foreach ($shopOrder->shopOrderItems as $orderItem)
    {
        $weight = '2';
        $width = '20';
        $height = '20';
        $length = '20';

        if ($orderItem->shopProduct) {
            if ($orderItem->shopProduct->weight) {
                $weight = $orderItem->shopProduct->weight;
            }
            if ($orderItem->shopProduct->width) {
                $width = round($orderItem->shopProduct->width/10);
            }
            if ($orderItem->shopProduct->length) {
                $length = round($orderItem->shopProduct->length/10);
            }
            if ($orderItem->shopProduct->height) {
                $height = round($orderItem->shopProduct->height/10);
            }
        }


        for ($i = 1; $i <= (int) $orderItem->quantity; $i++) {
            $goods[] = [
                "weight" => $weight,
                "length" => $length,
                "width" => $width,
                "height" => $height,
            ];
        }

    }
}

//$widgetData = (array) \Yii::$app->request->get("cdek");
$widgetData = [
    'apiKey'            => \Yii::$app->yaMap->api_key,
    'from'            => $cdekHandler->cityFrom ? $cdekHandler->cityFrom : "Москва",
    /*'from' => [
        'code' => '184'
    ],*/
    'defaultLocation' => $cdekHandler->defaultCity ? $cdekHandler->defaultCity : "Москва",
    'servicePath'     => \yii\helpers\Url::to(['calculate', 'delivery_id' => $shopDelivery->id, 'order_id' => $shopOrder->id]),
    'goods'     => $goods,
];
$parentWidgetId = \yii\helpers\ArrayHelper::getValue(\Yii::$app->request->get("options"), 'id');

$jsData = \yii\helpers\Json::encode($widgetData);
\Yii::$app->seo->countersContent = '';

?>
<style>
    html, body {
        margin: 0;
        padding: 0;
    }
</style>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Пример работы виджета ПВЗ</title>
    <script src="https://cdn.jsdelivr.net/npm/@cdek-it/widget@3" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/@unocss/runtime" type="text/javascript"></script>
    <link href="https://cdn.jsdelivr.net/npm/@unocss/reset/tailwind.min.css" rel="stylesheet">
    <!--<script id="ISDEKscript" type="text/javascript" src="https://widget.cdek.ru/widget/widjet.js" charset="utf-8"></script>-->
</head>
<body>
<script type="text/javascript">
    var parentElementId = "<?php echo $parentWidgetId; ?>";
    var jsData = <?php echo $jsData; ?>;


    if (typeof window.parent.$ === 'undefined') {
        console.log('no jquery');
        window.parent.addEventListener('load', function () {
            alert("В родительском окне нет нужной библиотеки jquery. Пожалуйста, сообщите разработчикам!")
        })
    }


    var jParentWidget = window.parent.$("#" + parentElementId);

    var jsData2 = {
        "root": 'cdek-map',

        canChoose: true,
        sender: true,

        hideFilters: {
            have_cashless: true,
            have_cash: true,
            is_dressing_room: true,
            type: false,
        },

        hideDeliveryOptions: {
            office: false,
            door: true,
        },

        onChoose(type, tariff, address) {
           jParentWidget.trigger("select", {
                'data': {
                    'type': type,
                    'tariff': tariff,
                    'address': address,
                },
            });
       },

        onCalculate(type, tariff, address) {
           console.log(type);
           console.log(tariff);
           console.log(address);
       },
    };


    Object.assign(jsData, jsData2);

    document.addEventListener('DOMContentLoaded', () => {
        var widget = new window.CDEKWidget(jsData);
    });



</script>

<!--<div id="forpvz" style="height:500px;"></div>-->
<div id="cdek-map" class="w-full h-[600px]"></div>

</body>
</html>