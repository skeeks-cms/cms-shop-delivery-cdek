<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\shop\cdek\controllers;

use yii\web\Controller;

/**
 * Class AdminExportTaskController
 * @package skeeks\cms\export\controllers
 */
class CdekController extends Controller
{
    public function actionMap()
    {
        return $this->renderPartial($this->action->id);
    }
}