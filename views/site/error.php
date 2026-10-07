<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = $name;
$this->params['noindex'] = true;
?>
<div class="container" style="padding:72px 16px">
    <h1><?= Html::encode($exception instanceof \yii\web\NotFoundHttpException ? 'Page not found' : $name) ?></h1>
    <p class="text-muted"><?= nl2br(Html::encode($message)) ?></p>
    <p><a href="<?= Url::to(['/site/index']) ?>">Go to EntityIndex home</a></p>
</div>
