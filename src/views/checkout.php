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
    /*'defaultCity' => $widget->deliveryHandler->defaultCity ? $widget->deliveryHandler->defaultCity : "auto",
    'cityFrom' => $widget->deliveryHandler->cityFrom,*/

    /*'link' => "forpvz",*/
    /*'hideMessages' => false,
    'hidedress' => false,
    'bymapcoord' => false,
    'hidecash' => false,
    'hidedelt' => false,
    'detailAddress' => false,*/
];

/*if (isset(\Yii::$app->yaMap) && \Yii::$app->yaMap->api_key) {
    $cdekWidget['apikey'] = \Yii::$app->yaMap->api_key;
}*/

$iframeUrl = \yii\helpers\Url::to(['/cdek/cdek/map',
    //'cdek' => $cdekConfig,
    'delivery_id' => $checkoutModel->delivery->id,
    'order_id' => $widget->shopOrder->id,
    'options' => [
        'id' => $widget->id
    ]]
);

$json = \yii\helpers\Json::encode([
    'id' => $widget->id,
    'iframeUrl' => $iframeUrl,
    'orderId' => $widget->shopOrder->id,
    'deliveryId' => $checkoutModel->delivery->id,
    'calculationHash' => $checkoutModel->deliveryCalculationHash,
    'isChooseTariff' => (bool)$widget->deliveryHandler->isChooseTariff,
    'isMapOpen' => !$checkoutModel->address,
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
        var mapCalculationHash = self.get('calculationHash') || '';
        var isMapOpen = self.get('isMapOpen');
        var ensureMap = function() {
            if (!isMapOpen || self.getJMapWidget().find('iframe').length) {
                return;
            }
            var url = self.get('iframeUrl');
            self.getJMapWidget().append($('<iframe>', {
                src: url + (url.indexOf('?') === -1 ? '?' : '&') +
                    '_deliveryRevision=' + encodeURIComponent(mapCalculationHash)
            }));
        };

        // Cart AJAX may update totals without rendering the delivery widget again.
        $(document).off('ajaxSuccess.sxCdekDelivery').on('ajaxSuccess.sxCdekDelivery', function(e, xhr) {
            var response = xhr.responseJSON;
            var order = response && response.data;
            if (!order || String(order.id) !== String(self.get('orderId')) ||
                String(order.shop_delivery_id) !== String(self.get('deliveryId')) || !order.deliveryCalculation) {
                return;
            }
            var error = order.deliveryCalculation.error || '';
            self.getJWidget().find('.sx-delivery-calculation-error').text(error).toggle(Boolean(error));
            // The iframe embeds parcels at creation; its old tariff list cannot be reused.
            var inputHash = order.deliveryCalculation.inputHash || '';
            if (self.get('isChooseTariff') && inputHash !== mapCalculationHash) {
                mapCalculationHash = inputHash;
                self.getJMapWidget().empty();
            }
            ensureMap();
        });
        
        ensureMap();
        
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
            self.getJWidget().find('.sx-delivery-calculation-error').hide();
            
            console.log(chooseData);
            
            $("#cdekcheckoutmodel-name").val(chooseData.address.name);
            $("#cdekcheckoutmodel-address").val(chooseData.address.address);
            $("#cdekcheckoutmodel-id").val(chooseData.address.code);
            $("#cdekcheckoutmodel-worktime").val(chooseData.address.work_time);
            /*$("#cdekcheckoutmodel-phone").val(chooseData.address.Phone);*/
            $("#cdekcheckoutmodel-city").val(chooseData.address.city);
            $("#cdekcheckoutmodel-tariffcode").val(chooseData.tariff ? chooseData.tariff.tariff_code : "");
            //Если включен рассчет доставки
            if ($("#cdekcheckoutmodel-price").length) {
                // Виджет v3 возвращает стоимость в выбранном тарифе.
                var deliveryPrice = chooseData.tariff ? chooseData.tariff.delivery_sum : chooseData.price;
                $("#cdekcheckoutmodel-price").val(deliveryPrice == null ? "" : String(deliveryPrice));
            }
            
            if (chooseData.address.work_time) {
                $(".sx-cdek-worktime .sx-value", self.getJWidget()).empty().append(chooseData.address.work_time);
                $(".sx-cdek-worktime", self.getJWidget()).show();
            } else {
                $(".sx-cdek-worktime", self.getJWidget()).hide();
            }
            
            if (chooseData.address.city) {
                $(".sx-cdek-city .sx-value", self.getJWidget()).empty().append(chooseData.address.city);
                $(".sx-cdek-city", self.getJWidget()).show();
            } else {
                $(".sx-cdek-city", self.getJWidget()).hide();
            }
            
            /*if (chooseData.PVZ.Phone) {
                $(".sx-cdek-phone .sx-value", self.getJWidget()).empty().append(chooseData.PVZ.Phone);
                $(".sx-cdek-phone", self.getJWidget()).show();
            } else {
                $(".sx-cdek-phone", self.getJWidget()).hide();
            }*/
            
            $(".sx-cdek-address", self.getJWidget()).empty().append(chooseData.address.address);
            self.getJAddressWidget().fadeIn();
            isMapOpen = false;
            // Unload the provider map, rather than keeping its heavy runtime hidden.
            self.getJMapWidget().stop(true, true).hide().empty();
            
            setTimeout(function() {
                $("#cdekcheckoutmodel-address").trigger("change");
            });
        });
        
        $(".sx-tirgger-cdek-map", self.getJWidget()).on("click", function() {
            isMapOpen = true;
            self.getJMapWidget().empty();
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
            
            // Build once after the server has stored the cleared selection.
            // ajaxSuccess above then loads current parcels and the latest revision.
            self.getJForm().submit();
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
    height: 600px;
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
    <p class="sx-delivery-calculation-error text-danger" role="alert"<?php echo $checkoutModel->deliveryCalculationError ? '' : ' style="display: none;"'; ?>><?php echo \yii\helpers\Html::encode($checkoutModel->deliveryCalculationError); ?></p>
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
        <?php if($widget->deliveryHandler->isChooseTariff) : ?>
            <?php echo $form->field($checkoutModel, 'tariffCode'); ?>
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
