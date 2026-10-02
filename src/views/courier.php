<?php
/** @var $widget \skeeks\cms\shop\cdek\CdekCheckoutWidget */
/** @var $checkoutModel \skeeks\cms\shop\cdek\CdekCheckoutModel */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
$config = Json::encode([
    'id' => $widget->id, 'orderId' => $widget->shopOrder->id,
    'deliveryId' => $checkoutModel->delivery->id,
    'calculated' => (bool)$widget->deliveryHandler->isChooseTariff,
    'citiesUrl' => Url::to(['/cdek/cdek/cities']),
    'tariffsUrl' => Url::to(['/cdek/cdek/courier-tariffs']),
]);
$this->registerJs(<<<JS
(function(config) {
    var root = $('#' + config.id), form = root.find('form');
    var input = function(name) { return form.find('[name="CdekCheckoutModel[' + name + ']"]'); };
    var feedback = root.find('[data-sx-cdek-feedback]');
    var status = root.find('[data-sx-cdek-status]');
    var retry = root.find('[data-sx-cdek-retry]');
    var cities = root.find('[data-sx-cdek-cities]');
    var tariff = root.find('[data-sx-cdek-tariff]'), tariffCode = input('tariffCode'), revision = null, searchRequest, quoteRequest;
    var quoteSequence = 0, searchSequence = 0, searchTimer, dirty = false;
    var error = function(message) { feedback.text(message || '').toggle(Boolean(message)); };
    var ready = function() { return input('cityCode').val() && input('street').val().trim() && input('house').val().trim(); };
    var addressKey = function(data) {
        return JSON.stringify(['cityCode', 'street', 'house', 'flat'].map(function(name) {
            return String(data ? (data[name] == null ? '' : data[name]) : (input(name).val() || '')).trim();
        }));
    };
    var active = function() { return root.is(':visible'); };
    var clearTariffs = function() {
        quoteSequence++;
        if (quoteRequest) quoteRequest.abort();
        tariffCode.val('');
        tariff.empty().append($('<option>', {value: '', text: 'Выберите тариф'})).prop('disabled', true);
    };
    var loadTariffs = function() {
        if (!config.calculated || !ready() || dirty || !active()) return;
        var sequence = ++quoteSequence, selected = tariffCode.val(), destination = addressKey();
        retry.hide();
        if (quoteRequest) quoteRequest.abort();
        status.text('Рассчитываем стоимость доставки…');
        tariff.prop('disabled', true);
        quoteRequest = $.getJSON(config.tariffsUrl).done(function(response) {
            if (sequence !== quoteSequence || destination !== addressKey() || !active() || !root.closest('html').length) return;
            status.text('');
            if (!response.success) { clearTariffs(); error(response.message); retry.show(); return; }
            tariff.empty().append($('<option>', {value: '', text: 'Выберите тариф'}));
            (response.tariffs || []).forEach(function(item) {
                $('<option>', {value: item.tariff_code, text: item.tariff_name + ' — ' +
                    item.delivery_sum + ' ₽, ' + item.period_min + '–' + item.period_max + ' дн.'}).appendTo(tariff);
            });
            tariff.prop('disabled', false).val(selected || '');
            if (!tariff.val()) tariff.val('');
            if (!response.tariffs.length) error('СДЭК не предлагает доставку по этому адресу для текущей корзины. Укажите другой адрес или способ доставки.');
        }).fail(function(xhr, state) {
            if (state !== 'abort' && sequence === quoteSequence) {
                status.text(''); clearTariffs(); error('Не удалось загрузить тарифы СДЭК. Повторите расчёт.'); retry.show();
            }
        });
    };
    var save = function() { dirty = true; form.submit(); };
    var invalidate = function() {
        dirty = true;
        clearTariffs(); retry.hide(); status.text(''); error('');
    };
    var searchCities = function() {
        var query = input('city').val().trim();
        if (query.length < 2) return;
        var sequence = ++searchSequence;
        if (searchRequest) searchRequest.abort();
        cities.empty(); status.text('Ищем город…');
        searchRequest = $.getJSON(config.citiesUrl, {query: query}).done(function(response) {
            if (sequence !== searchSequence) return;
            status.text('');
            if (!response.success) { error(response.message); return; }
            error('');
            (response.cities || []).forEach(function(city) {
                $('<button>', {type: 'button', text: city.name, 'data-sx-cdek-city-option': city.code})
                    .on('click', function() {
                        input('cityCode').val(city.code); input('city').val(city.name);
                        cities.empty(); invalidate(); save();
                    }).appendTo(cities);
            });
            if (!response.cities.length) error('Город не найден. Проверьте название и повторите поиск.');
        }).fail(function(xhr, state) {
            if (state !== 'abort' && sequence === searchSequence) { status.text(''); error('Не удалось загрузить города. Повторите поиск.'); }
        });
    };
    input('city').on('input', function() {
        clearTimeout(searchTimer);
        searchSequence++; if (searchRequest) searchRequest.abort();
        input('cityCode').val(''); cities.empty(); invalidate();
        status.text('');
        searchTimer = setTimeout(searchCities, 400);
    }).on('change', save).on('keydown', function(event) {
        if (event.key === 'Enter') { event.preventDefault(); clearTimeout(searchTimer); searchCities(); }
        if (event.key === 'Escape') { clearTimeout(searchTimer); searchSequence++; cities.empty(); status.text(''); }
        if (event.key === 'ArrowDown' && cities.find('button').length) { event.preventDefault(); cities.find('button').first().trigger('focus'); }
    });
    input('street').add(input('house')).add(input('flat')).on('input', invalidate).on('change', save);
    input('entrance').add(input('floor')).add(input('comment')).on('change', save);
    tariff.on('change', function() { tariffCode.val(tariff.val() || ''); save(); });
    root.find('[data-sx-cdek-retry]').on('click', function() { revision = null; save(); });
    form.on('change-delivery', save);
    var namespace = '.sxCdekDelivery' + config.id;
    $(document).off('click' + namespace).on('click' + namespace, '.sx-delivery', function() {
        if (String($(this).data('id')) !== String(config.deliveryId)) {
            quoteSequence++; searchSequence++; clearTimeout(searchTimer);
            if (quoteRequest) quoteRequest.abort(); if (searchRequest) searchRequest.abort();
            cities.empty(); status.text(''); revision = null;
        }
    });
    $(document).off('ajaxSuccess' + namespace).on('ajaxSuccess' + namespace, function(e, xhr) {
        var response = xhr.responseJSON, order = response && response.data;
        if (!order || String(order.id) !== String(config.orderId) || String(order.shop_delivery_id) !== String(config.deliveryId)
            || !order.deliveryCalculation) return;
        var stored;
        try { stored = JSON.parse(order.delivery_handler_data_jsoned || '{}'); } catch (e) { return; }
        dirty = addressKey(stored) !== addressKey();
        if (dirty) return;
        error(order.deliveryCalculation.error);
        var next = order.deliveryCalculation.inputHash || '';
        if (config.calculated && next !== revision) { revision = next; loadTariffs(); }
    });
    // Cart forms are submitted by the surrounding shop widget. No map scripts are loaded here.
    loadTariffs();
})({$config});
JS);
$this->registerCss(<<<CSS
.sx-cdek-courier .sx-cdek-address-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 16px; }
.sx-cdek-courier .sx-cdek-city-field { position:relative; }
.sx-cdek-courier [data-sx-cdek-cities] { position:absolute; top:100%; left:0; right:0; z-index:100; max-height:280px; overflow:auto; background:#fff; color:#333; box-shadow:0 5px 16px rgba(0,0,0,.16); border:1px solid #ddd; }
.sx-cdek-courier [data-sx-cdek-cities]:empty { display:none; }
.sx-cdek-courier button { padding:10px 14px; border:1px solid #ccc; background:transparent; border-radius:6px; color:inherit; cursor:pointer; }
.sx-cdek-courier button:hover { background:#f5f5f5; }
.sx-cdek-courier button:focus-visible { outline:2px solid currentColor; outline-offset:2px; }
.sx-cdek-courier [data-sx-cdek-cities] button { display:block; width:100%; text-align:left; border:0; border-radius:0; min-height:44px; }
.sx-cdek-courier [data-sx-cdek-status] { margin:12px 0; }
@media(max-width:480px) { .sx-cdek-courier .sx-cdek-address-grid { grid-template-columns:1fr; } }
CSS);
?>
<div class="sx-cdek-widget sx-cdek-courier" id="<?= Html::encode($widget->id) ?>">
    <p>Укажите адрес, по которому курьер СДЭК доставит заказ.</p>
    <p class="sx-delivery-calculation-error text-danger" data-sx-cdek-feedback role="alert" <?= $checkoutModel->deliveryCalculationError ? '' : 'style="display:none"' ?>><?= Html::encode($checkoutModel->deliveryCalculationError) ?></p>
    <?php $form = \yii\bootstrap\ActiveForm::begin(['enableClientValidation' => false]); ?>
    <?= Html::activeHiddenInput($checkoutModel, 'cityCode') ?>
    <div class="sx-cdek-city-field">
    <?= $form->field($checkoutModel, 'city')->textInput(['id' => $widget->id . '-city', 'autocomplete' => 'off', 'aria-controls' => $widget->id . '-cities'])->hint(false) ?>
    <div id="<?= Html::encode($widget->id . '-cities') ?>" data-sx-cdek-cities aria-label="Найденные города" aria-live="polite"></div>
    </div>
    <p class="help-block">Начните вводить название города и выберите населённый пункт из подсказок СДЭК. Для поиска достаточно двух букв.</p>
    <div class="sx-cdek-address-grid">
        <?= $form->field($checkoutModel, 'street')->textInput(['autocomplete' => 'address-line1', 'maxlength' => 255, 'required' => true]) ?>
        <?= $form->field($checkoutModel, 'house')->textInput(['maxlength' => 255, 'required' => true]) ?>
        <?= $form->field($checkoutModel, 'flat')->textInput(['autocomplete' => 'address-line2', 'maxlength' => 255]) ?>
        <?= $form->field($checkoutModel, 'entrance')->textInput(['maxlength' => 255]) ?>
        <?= $form->field($checkoutModel, 'floor')->textInput(['maxlength' => 255]) ?>
    </div>
    <?= $form->field($checkoutModel, 'comment')->textarea(['rows' => 2, 'maxlength' => 1000]) ?>
    <?php if ($widget->deliveryHandler->isChooseTariff): ?>
        <?= Html::activeHiddenInput($checkoutModel, 'tariffCode', ['id' => $widget->id . '-tariff-code']) ?>
        <?= $form->field($checkoutModel, 'tariffCode')->dropDownList($checkoutModel->tariffCode ? [$checkoutModel->tariffCode => 'Загружаем выбранный тариф…'] : [], ['id' => $widget->id . '-tariff', 'name' => null, 'data-sx-cdek-tariff' => true, 'prompt' => 'Выберите тариф']) ?>
        <button type="button" data-sx-cdek-retry style="display:none">Повторить расчёт</button>
    <?php endif ?>
    <p data-sx-cdek-status role="status" aria-live="polite"></p>
    <?php $form::end(); ?>
</div>
