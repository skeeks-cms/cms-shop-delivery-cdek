<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */
/**
 * @var $this yii\web\View
 * @var $widget \skeeks\cms\shop\cdek\CdekCheckoutWidget
 * @var $checkoutModel \skeeks\cms\shop\cdek\CdekCheckoutModel
 */
$widget = $this->context;
$checkoutModelCurrent = $widget->deliveryHandler->checkoutModel;
$checkoutModel = $widget->shopOrder->deliveryHandlerCheckoutModel;

if (!$checkoutModel instanceof $checkoutModelCurrent) {
    $checkoutModel = $checkoutModelCurrent;
}

$cdekConfig = [
    'defaultCity' => $widget->deliveryHandler->defaultCity ? $widget->deliveryHandler->defaultCity : "auto",
    'cityFrom' => $widget->deliveryHandler->cityFrom,

    /*'link' => "forpvz",*/
    'hideMessages' => false,
    'hidedress' => false,
    'bymapcoord' => false,
    'hidecash' => false,
    'hidedelt' => false,
    'detailAddress' => false,
];

/*if (isset(\Yii::$app->yaMap) && \Yii::$app->yaMap->api_key) {
    $cdekWidget['apikey'] = \Yii::$app->yaMap->api_key;
}*/

$iframeUrl = \yii\helpers\Url::to(['/cdek/cdek/map', 'cdek' => $cdekConfig, 'options' => [
    'id' => $widget->id
]]);

$json = \yii\helpers\Json::encode([
    'id' => $widget->id,
    'iframeUrl' => $iframeUrl,
]);

$this->registerJs(<<<JS

//Происходит когда пользователь меняет способ доставки в заказе
//Тут можно дополнительно сделать расчет цены и отправить данные
//apikey
sx.classes.CdekWidget = sx.classes.Component.extend({

    _init: function()
    {},
    
    _onDomReady: function()
    {
        var self = this;
        
        self.getJMapWidget().append(
            $("<iframe>", {
                'src' : self.get('iframeUrl')
            })
        );
        
        this.getJForm().on("change-delivery", function() {
            /*self.cdekWidget.open();*/
            $(this).submit();
        });
        
        $("#cdekcheckoutmodel-address", self.getJForm()).on("change", function () {
            
            setTimeout(function() {
                self.getJForm().submit();
            }, 300);
        });
        
        
        self.getJWidget().on("select", function(e, data){
            var chooseData = data.data;
            console.log(chooseData);
            
            $("#cdekcheckoutmodel-name").val(chooseData.PVZ.Name);
            $("#cdekcheckoutmodel-address").val(chooseData.PVZ.Address);
            $("#cdekcheckoutmodel-id").val(chooseData.PVZ.id);
            $("#cdekcheckoutmodel-worktime").val(chooseData.PVZ.WorkTime);
            $("#cdekcheckoutmodel-phone").val(chooseData.PVZ.Phone);
            $("#cdekcheckoutmodel-city").val(chooseData.cityName);
            //Если включен рассчет доставки
            if ($("#cdekcheckoutmodel-price").length) {
                $("#cdekcheckoutmodel-price").val(chooseData.price);
            }
            
            if (chooseData.PVZ.WorkTime) {
                $(".sx-cdek-worktime .sx-value", self.getJWidget()).empty().append(chooseData.PVZ.WorkTime);
                $(".sx-cdek-worktime", self.getJWidget()).show();
            } else {
                $(".sx-cdek-worktime", self.getJWidget()).hide();
            }
            
            if (chooseData.cityName) {
                $(".sx-cdek-city .sx-value", self.getJWidget()).empty().append(chooseData.cityName);
                $(".sx-cdek-city", self.getJWidget()).show();
            } else {
                $(".sx-cdek-city", self.getJWidget()).hide();
            }
            
            if (chooseData.PVZ.Phone) {
                $(".sx-cdek-phone .sx-value", self.getJWidget()).empty().append(chooseData.PVZ.Phone);
                $(".sx-cdek-phone", self.getJWidget()).show();
            } else {
                $(".sx-cdek-phone", self.getJWidget()).hide();
            }
            
            $(".sx-cdek-address", self.getJWidget()).empty().append(chooseData.PVZ.Address);
            self.getJAddressWidget().fadeIn();
            self.getJMapWidget().slideUp();
            
            setTimeout(function() {
                $("#cdekcheckoutmodel-address").trigger("change");
            });
        });
        
        $(".sx-tirgger-cdek-map", self.getJWidget()).on("click", function() {
            self.getJMapWidget().slideDown();
            self.getJAddressWidget().slideUp();
            
            
            $("#cdekcheckoutmodel-name").val("");
            $("#cdekcheckoutmodel-address").val("");
            $("#cdekcheckoutmodel-id").val("");
            $("#cdekcheckoutmodel-worktime").val("");
            $("#cdekcheckoutmodel-city").val("");
            $("#cdekcheckoutmodel-phone").val("");
            //Если включен рассчет доставки
            if ($("#cdekcheckoutmodel-price").length) {
                $("#cdekcheckoutmodel-price").val("");
            }
            
            setTimeout(function() {
                self.getJForm().submit();
            }, 300);
        });
    },
    
    getJForm: function()
    {
        return $("form", this.getJWidget());
    },
    
    getJWidget: function()
    {
        return $("#" + this.get("id"));
    },
    
    getJAddressWidget: function()
    {
        return $(".sx-selected-cdek-wrapper", this.getJWidget());
    },
    
    getJMapWidget: function()
    {
        return $(".sx-map-cdek-wrapper", this.getJWidget());
    }
});
 
new sx.classes.CdekWidget({$json});


JS
);
$this->registerCss(<<<CSS

.sx-cdek-widget iframe {
    border: none;
    width: 100%;
    height: 500px;
}

.sx-cdek-widget .sx-checked-icon {
    margin-right: 5px;
}

CSS
);

//\skeeks\cms\shop\cdek\CdekCheckoutWidgetAsset::register($this)
/*$this->registerJsFile("https://widget.cdek.ru/widget/widjet.js", [
    'id' => 'ISDEKscript',
    'depends' => [
        \yii\web\JqueryAsset::class
    ]
]);*/
?>

<div class="sx-cdek-widget" id="<?php echo $widget->id; ?>">
    <?php $form = \yii\bootstrap\ActiveForm::begin([
        'enableClientValidation' => false,
    ]); ?>

    <div class="cms-user-field sx-hidden">
        <?php echo $form->field($checkoutModel, 'id'); ?>
        <?php echo $form->field($checkoutModel, 'name'); ?>
        <?php echo $form->field($checkoutModel, 'address'); ?>
        <?php echo $form->field($checkoutModel, 'worktime'); ?>
        <?php echo $form->field($checkoutModel, 'phone'); ?>
        <?php echo $form->field($checkoutModel, 'city'); ?>
        <?php if($widget->deliveryHandler->isCalculatePrice) : ?>
            <?php echo $form->field($checkoutModel, 'price'); ?>
        <?php endif; ?>
    </div>

    <div class="sx-address-fields">


        <div class="sx-selected-cdek-wrapper <?php echo $checkoutModel->address ? : "sx-hidden"; ?>">
            <div class="sx-address btn btn-block btn-check sx-checked"
            >
                <div class="d-flex">
                    <!--<span class="sx-checked-icon my-auto" data-icon="✓">
                        ✓
                    </span>-->
                    <div class="sx-address-info">
                        <div class="sx-cdek-city <?php echo $checkoutModel->city ? "": "sx-hidden"; ?>">
                            <span class="sx-value"><?php echo $checkoutModel->city ? $checkoutModel->city : "нет"; ?></span>
                        </div>
                        <div class="sx-cdek-address">
                            <?php echo $checkoutModel->address; ?>
                        </div>
                        <div class="sx-cdek-phone <?php echo $checkoutModel->phone ? "": "sx-hidden"; ?>">
                            Телефон: <span class="sx-value"><?php echo $checkoutModel->phone ? $checkoutModel->phone : "нет"; ?></span>
                        </div>
                        <div class="sx-cdek-worktime <?php echo $checkoutModel->worktime ? "": "sx-hidden"; ?>">
                            Время работы: <span class="sx-value"><?php echo $checkoutModel->worktime ? $checkoutModel->worktime : ""; ?></span>
                        </div>
                    </div>
                </div>

            </div>
            <div class="sx-tirgger-cdek-map btn btn-block btn-check">
                Выбрать другой пункт
            </div>
        </div>

        <div class="sx-map-cdek-wrapper <?php echo $checkoutModel->address ? "sx-hidden": ""; ?>">
            <!--<iframe src="<?php /*echo $iframeUrl; */?>"></iframe>-->
        </div>

    </div>
    <? $form::end(); ?>
</div>
