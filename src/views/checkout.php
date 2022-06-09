<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */
/**
 * @var $this yii\web\View
 * @var $widget \skeeks\cms\shop\delivery\cdek\CdekCheckoutWidget
 * @var $checkoutModel \skeeks\cms\shop\cdek\CdekCheckoutModel
 */
$widget = $this->context;
$checkoutModelCurrent = $widget->deliveryHandler->checkoutModel;
$checkoutModel = $widget->shopOrder->deliveryHandlerCheckoutModel;

if (!$checkoutModel instanceof $checkoutModelCurrent) {
    $checkoutModel = $checkoutModelCurrent;
}

$this->registerJs(<<<JS

$(".sx-simple-widget").on("click", ".btn-check", function() {
    $(".btn-check", $(".sx-simple-widget")).removeClass("sx-checked");
    $(".sx-checked-icon", $(".sx-simple-widget")).empty();
    $(this).addClass("sx-checked");
    $(".sx-checked-icon", $(this)).append($(".sx-checked-icon", $(this)).data("icon")).append(" ");
    
    $("#simplecheckoutmodel-cms_user_address_id").val($(this).data("id")).change();
});

//Происходит когда пользователь меняет способ доставки в заказе
//Тут можно дополнительно сделать расчет цены и отправить данные

sx.classes.CdekWidget = sx.classes.Component.extend({

    _init: function()
    {},
    
    _onDomReady: function()
    {
        var self = this;
        
        this.getJForm().on("change-delivery", function() {
            $(this).submit();
        });
        
        $("select, input, textarea", self.getJForm()).on("change", function () {
            
            setTimeout(function() {
                self.getJForm().submit();
            }, 300);
        });
        
        
        var widjet = new ISDEKWidjet({
            hideMessages: false,
            /*defaultCity: 'Москва',*/
            cityFrom: 'Москва',
            choose: true,
            link: 'forpvz',
            hidedress: false,
            bymapcoord: false,
            hidecash: false,
            hidedelt: true,
            detailAddress: true,
            /*goods: [{
                length: 10,
                width: 10,
                height: 10,
                weight: 1
            }],*/
            onChoose: function(wat) {
                
                console.log(wat);
                /*var Name = wat.cityName + " " + wat.PVZ.Address;
                $("#forpvz-selected").empty().append(Name).append(Change).slideDown();
                $("#forpvz").slideUp();
                $("#relatedpropertiesmodel-sdek").val(Name).change();*/
                
            },
        });

    },
    
    getJForm: function()
    {
        return $("form", ".sx-cdek-widget");
    }
});
 
new sx.classes.CdekWidget();


JS
);
$this->registerCss(<<<CSS

.sx-simple-widget .sx-checked-icon {
    margin-right: 5px;
}

.populated .sx-trigger-show-map {
    font-size: 10px;
    top: 4px;
}

.sx-address {
    text-align: left;
}

CSS
);
?>

<div class="sx-cdek-widget">
    <?php $form = \yii\bootstrap\ActiveForm::begin([
        'enableClientValidation' => false,
    ]); ?>

    <div class="cms-user-field">
        <?php echo $form->field($checkoutModel, 'name'); ?>
        <?php echo $form->field($checkoutModel, 'address'); ?>
    </div>

    <div class="sx-address-fields">
        <script id="ISDEKscript" type="text/javascript" src="https://widget.cdek.ru/widget/widjet.js" charset="utf-8"></script>
    </div>
    <? $form::end(); ?>
</div>
