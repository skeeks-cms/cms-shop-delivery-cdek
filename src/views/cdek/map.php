<?php
/**
* @var $this yii\web\View
*/

$widgetData = \Yii::$app->request->get("cdek");
$parentWidgetId = \yii\helpers\ArrayHelper::getValue(\Yii::$app->request->get("options"), 'id');

$jsData = \yii\helpers\Json::encode(\yii\helpers\ArrayHelper::merge($widgetData, [
    'link' => 'forpvz',
]));
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
    <script id="ISDEKscript" type="text/javascript" src="https://widget.cdek.ru/widget/widjet.js" charset="utf-8"></script>
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
        detailAddress: true,
        choose: true,

        //В виджете скрыты варианты доставки: курьер или ПВЗ.
        hidedelt: true,

        //В виджете скрыты фильтры для отображения ПВЗ
        hidedress: true,
        hidecash: true,

        //В виджете скрыт фильтр для отображения ПВЗ с примеркой.
        hidedress: true,

                    bymapcoord: false,
                    hidecash: false,
                    hidedelt: true,
                    detailAddress: true,

        onChoose: function(wat) {
            jParentWidget.trigger("select", {
                'data' : wat,
            });
        },
    };

    Object.assign(jsData, jsData2);

    var widjet = new ISDEKWidjet(jsData);
</script>

<div id="forpvz" style="height:500px;"></div>

</body>
</html>