<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\shop\cdek\controllers;

use skeeks\cms\shop\cdek\CdekDeliveryHandler;
use skeeks\cms\shop\cdek\CdekService;
use skeeks\cms\shop\cdek\Service;
use skeeks\cms\shop\models\ShopDelivery;
use skeeks\cms\shop\models\ShopOrder;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Class AdminExportTaskController
 * @package skeeks\cms\export\controllers
 */
class CdekController extends Controller
{
    public $enableCsrfValidation = false;

    /** Public checkout helpers use only the visitor's current draft cart. */
    private function getCourierModel()
    {
        $order = \Yii::$app->shop->shopUser->shopOrder;
        $model = $order ? $order->deliveryHandlerCheckoutModel : null;
        if (!$order || $order->is_created || !$model instanceof \skeeks\cms\shop\cdek\CdekCheckoutModel
            || !$model->deliveryHandler->isCourier()) {
            throw new NotFoundHttpException('Выберите курьерскую доставку СДЭК.');
        }
        return $model;
    }

    public function actionCities($query = '')
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        try {
            $model = $this->getCourierModel();
            $cities = $model->deliveryHandler->createService()->searchCities($query);
            return ['success' => true, 'cities' => array_map(static function ($city) {
                return ['code' => (int)$city['code'], 'name' => $city['city']
                    . (!empty($city['region']) ? ', ' . $city['region'] : '')
                    . (!empty($city['country']) ? ', ' . $city['country'] : '')];
            }, $cities)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Не удалось загрузить города СДЭК. Повторите поиск.'];
        }
    }

    public function actionCourierTariffs()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        try {
            $model = $this->getCourierModel();
            return ['success' => true, 'tariffs' => $model->getAvailableTariffs()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Не удалось рассчитать доставку. Проверьте адрес и повторите попытку.'];
        }
    }

    public function actionMap()
    {
        $deliveryId = \Yii::$app->request->get("delivery_id");
        $shopDelivery = ShopDelivery::findOne((int)$deliveryId);
        if (!$shopDelivery) {
            throw new NotFoundHttpException("Не указан способ доставки!");
        }

        $order_id = \Yii::$app->request->get("order_id");
        $shopOrder = ShopOrder::findOne((int)$order_id);
        if (!$shopOrder) {
            throw new NotFoundHttpException("Не казан заказ!");
        }


        return $this->renderPartial($this->action->id, [
            'shopDelivery' => $shopDelivery,
            'shopOrder' => $shopOrder,
        ]);
    }

    public function actionCalculate()
    {
        $deliveryId = \Yii::$app->request->get("delivery_id");
        $shopDelivery = ShopDelivery::findOne((int)$deliveryId);
        if (!$shopDelivery) {
            throw new NotFoundHttpException("Доставка не найдена!");
        }

        $order_id = \Yii::$app->request->get("order_id");
        $shopOrder = ShopOrder::findOne((int)$order_id);
        if (!$shopOrder) {
            throw new NotFoundHttpException("Не казан заказ!");
        }

        /**
         * @var $handler CdekDeliveryHandler
         */
        $handler = $shopDelivery->handler;


        $service = new CdekService($handler->account, $handler->secure);
        $service->allowedDeliveryModes = array_values(array_filter(range(1, 6), [$handler, 'allowsDeliveryMode']));


        $service->process($_GET, file_get_contents('php://input'));
    }
}
