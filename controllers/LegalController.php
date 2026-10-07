<?php

namespace app\controllers;

use yii\web\Controller;

class LegalController extends Controller
{
    public function actionPrivacy()
    {
        return $this->render('privacy');
    }

    public function actionTerms()
    {
        return $this->render('terms');
    }
}
