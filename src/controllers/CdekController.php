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


        $service->process($_GET, file_get_contents('php://input'));
    }
}