<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\HttpException;

$this->title = $name;
$this->params['noindex'] = true;
$code = $exception instanceof HttpException ? $exception->statusCode : 500;
$heading = $exception instanceof \yii\web\NotFoundHttpException ? 'Page not found' : $name;
?>
<section class="error-page">
    <div class="container">
        <div class="error-code"><?= Html::encode($code) ?></div>
        <h1><?= Html::encode($heading) ?></h1>
        <p class="text-muted"><?= nl2br(Html::encode($message)) ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-ei" href="<?= Url::to(['/site/index']) ?>">Go to home</a>
            <a class="btn btn-outline-secondary" href="<?= Url::to(['/site/contact']) ?>">Contact us</a>
        </div>
    </div>
</section>
