<?php
// php tests/delivery-recalculation.php <vendor/autoload.php>
define('YII_ENABLE_ERROR_HANDLER', false);
require $argv[1];
require dirname($argv[1]) . '/yiisoft/yii2/Yii.php';

use skeeks\cms\shop\cdek\CdekCheckoutModel;
use skeeks\cms\shop\cdek\CdekDeliveryHandler;
use skeeks\cms\shop\cdek\CdekService;
use skeeks\cms\shop\models\ShopOrder;
use skeeks\cms\shop\models\ShopOrderItem;
use skeeks\cms\money\Money;

$app = new yii\console\Application(['id' => 'delivery-test', 'basePath' => __DIR__, 'components' => [
    'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
    'cache' => ['class' => yii\caching\ArrayCache::class],
]]);
$app->set('money', new class extends yii\base\Component { public $baseCurrenciesData = []; });
$app->db->createCommand('CREATE TABLE test_order (id INTEGER PRIMARY KEY, amount REAL, delivery_amount REAL, tax_amount REAL, discount_amount REAL, delivery_handler_data_jsoned TEXT, updated_at INTEGER)')->execute();
$app->db->createCommand('CREATE TABLE shop_order_item (id INTEGER PRIMARY KEY)')->execute();
function verify($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS: $message\n"; }

class TestCdekService extends CdekService
{
    public $calls = 0;
    public $fail = false;
    public $unavailable = false;
    public $zero = false;
    public $lastRequest;
    protected function httpRequest($method, $data, $form = false, $json = false)
    {
        if ($method === 'deliverypoints') return ['result' => json_encode([['code' => 'TEST1', 'location' => ['city_code' => 44]]])];
        $this->calls++;
        $this->lastRequest = $data;
        if ($this->fail) throw new RuntimeException('Simulated API failure');
        $sum = $this->zero ? 0 : count($data['packages']) * 100;
        return ['result' => json_encode(['tariff_codes' => $this->unavailable ? [] : [['tariff_code' => 136, 'delivery_mode' => 4, 'delivery_sum' => $sum]]])];
    }
}
class TestCdekHandler extends CdekDeliveryHandler
{
    public $testService;
    public function createService() { return $this->testService; }
}
class TestOrder extends ShopOrder
{
    public $items = [];
    public $checkout;
    public $currency_code = 'RUB';
    public $is_created = 0;
    public $transitioning = false;
    public function init() {} // isolate infrastructure; retain real calculation and persistence methods
    public static function tableName() { return 'test_order'; }
    public function getShopOrderItems() { return $this->items; }
    public function getDeliveryHandlerCheckoutModel() { return $this->checkout; }
    public function isAttributeChanged($name, $identical = true) { return $name === 'is_created' ? $this->transitioning : parent::isAttributeChanged($name, $identical); }
    public function getCalcMoneyVat() { return new Money('0', 'RUB'); }
    public function getCalcMoneyDiscount() { return new Money('0', 'RUB'); }
    public function getCalcMoneyDelivery() { return $this->checkout->money; }
    public function getCalcMoney() { return new Money((string)(1000 + (float)$this->delivery_amount), 'RUB'); }
}
class TestItem extends ShopOrderItem
{
    public $order;
    public function init() {}
    public function getShopOrder() { return $this->order; }
}
$service = new TestCdekService('test', 'test');
$handler = new TestCdekHandler(['isChooseTariff' => 1, 'testService' => $service]);
$order = new TestOrder();
$product = (object)['weight' => 21000, 'width' => 400, 'length' => 200, 'height' => 500];
$order->items = [(object)['quantity' => 1, 'shopProduct' => $product]];
$model = new CdekCheckoutModel(['deliveryHandler' => $handler, 'delivery' => (object)['money' => new Money('350', 'RUB')], 'shopOrder' => $order, 'id' => 'TEST1', 'tariffCode' => 136, 'price' => '1']);
$order->checkout = $model;
$before = $after = 0;
$order->on(ShopOrder::EVENT_BEFORE_RECALCULATE, function () use (&$before) { $before++; });
$order->on(ShopOrder::EVENT_AFTER_RECALCULATE, function () use (&$after) { $after++; });
$order->recalculate();
verify($model->price === '100' && (float)$order->amount === 1100.0, 'Server quote replaces browser amount and updates total');
verify($before === 1 && $after === 1, 'Existing order calculation events retained');
verify($service->lastRequest['packages'][0] === ['weight' => 21000, 'length' => 20, 'width' => 40, 'height' => 50], 'Widget/server parcels agree in grams and cm');
$order->recalculate();
verify($service->calls === 1, 'Unchanged cart reuses fresh quote');
$item = new TestItem(); $item->order = $order;
$order->items[0]->quantity = 3;
$item->afterSaveCallback(new yii\base\Event());
verify($model->price === '300' && (float)$order->delivery_amount === 300.0, 'Quantity mutation uses common item-save hook');
verify(json_decode($order->delivery_handler_data_jsoned, true)['price'] === '300', 'Recalculated price persisted with order');
$order->items[] = (object)['quantity' => 1, 'shopProduct' => $product];
$item->afterSaveCallback(new yii\base\Event());
verify($model->price === '400', 'Adding an item recalculates');
array_pop($order->items); $order->items[0]->quantity = 1;
$item->afterSaveCallback(new yii\base\Event());
verify($model->price === '100', 'Removing an item/decreasing quantity recalculates');
$saved = $model->getStoredDeliveryData();
$model->loadStoredDeliveryData($saved);
$calls = $service->calls;
$order->recalculate();
verify($service->calls === $calls, 'Persisted fresh quote survives cart reopen');
$model->deliveryCalculatedAt = time() - 301;
$order->recalculate();
verify($service->calls === $calls + 1, 'Expired quote recalculates');
$order->refreshDeliveryCalculation(true);
verify($service->calls === $calls + 2, 'Checkout forces server verification');
$hash = $model->deliveryCalculationHash;
$model->load(['deliveryCalculationHash' => 'forged', 'deliveryCalculatedAt' => PHP_INT_MAX], '');
verify($model->deliveryCalculationHash === $hash, 'POST cannot forge quote metadata');
$service->fail = true;
verify(!$order->refreshDeliveryCalculation(true) && $model->price === null && $model->money->amount === '0', 'API failure clears stale price and blocks checkout hook');
$service->fail = false; $service->unavailable = true;
verify(!$order->refreshDeliveryCalculation(true) && strpos($model->deliveryCalculationError, 'недоступен') !== false, 'Unavailable selected tariff requires reselection');
$service->unavailable = false; $service->zero = true;
verify($order->refreshDeliveryCalculation(true) && $model->money->amount === '0' && !$model->deliveryCalculationError, 'Valid zero quote supported');
$model->tariffCode = null;
verify(!$order->refreshDeliveryCalculation(true), 'Legacy selection without tariff cannot retain stale price');
$handler->isChooseTariff = 0;
verify($order->refreshDeliveryCalculation(true) && $model->money->amount === '350', 'Fixed-price mode remains independent of API errors');
$handler->isChooseTariff = 1;
$order->is_created = 1;
$calls = $service->calls;
verify($order->refreshDeliveryCalculation(true) && $service->calls === $calls, 'Completed order not requoted');
$fixed = new class extends skeeks\cms\shop\delivery\DeliveryCheckoutModel {};
verify(!$fixed->supportsAutomaticCalculation() && $fixed->refreshDeliveryPrice(true), 'Existing delivery adapters remain opt-out');

// Exercise the actual checkout action without creating a real customer/order.
$order->is_created = 0;
$model->tariffCode = 136;
$service->zero = false;
$service->unavailable = true;
$app->set('request', new class extends yii\web\Request {
    public function getIsAjax() { return true; }
    public function getIsPost() { return true; }
});
$app->set('response', new yii\web\Response());
$app->set('shop', new class($order) extends yii\base\Component {
    public $shopUser;
    public function __construct($order) { $this->shopUser = (object)['shopOrder' => $order]; parent::__construct(); }
});
$controller = new skeeks\cms\shop\controllers\CartController('cart', new yii\base\Module('shop'));
$response = $controller->actionOrderCheckout();
verify(!$response->success && strpos($response->message, 'недоступен') !== false && !$order->is_created,
    'Checkout action rejects unavailable tariff before order creation');
verify($app->db->getTransaction() === null, 'Rejected checkout rolls back its transaction');

class TestMapService extends CdekService
{
    public $requests = [];
    public function points(array $params)
    {
        $property = new ReflectionProperty(CdekService::class, 'requestData');
        $property->setAccessible(true);
        $property->setValue($this, $params);
        return $this->getOfficesByCoordinates();
    }
    protected function httpRequest($method, $data, $form = false, $json = false)
    {
        $this->requests[] = [$method, $data];
        return ['result' => '[]', 'addedHeaders' => []];
    }
}
$mapService = new TestMapService('test', 'test');
$bounds = ['action' => 'byCoordinate', 'latitude_right_top' => '55.9', 'longitude_right_top' => '37.8',
    'latitude_left_bottom' => '55.6', 'longitude_left_bottom' => '37.4', 'is_handout' => 'true', 'type' => 'PVZ'];
$mapService->points($bounds);
$expected = $bounds; unset($expected['action']);
verify($mapService->requests[0] === ['deliverypoints/byPolygons', $expected],
    'Map forwards visible bounds and pickup filters without full-list request');
$bounds['longitude_right_top'] = '38.1';
$mapService->points($bounds);
verify($mapService->requests[1][1]['longitude_right_top'] === '38.1', 'Moving map requests the new visible area');
try {
    $mapService->points(['action' => 'byCoordinate']);
    throw new RuntimeException('Missing bounds accepted');
} catch (InvalidArgumentException $e) {
    verify(count($mapService->requests) === 2, 'Missing bounds never fall back to loading all pickup points');
}
