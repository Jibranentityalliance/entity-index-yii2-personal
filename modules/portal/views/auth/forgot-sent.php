<?php

use yii\helpers\Html;
use yii\helpers\Url;
use app\models\User;

$this->title = 'Check your email';
?>
<p class="login-box-msg">Check your email</p>
<p>If an account exists for <strong><?= Html::encode($email) ?></strong>, we have sent it a link to choose a new password. The link works for <?= intdiv(User::RESET_TTL, 60) ?> minutes.</p>
<p class="text-muted">No email after a few minutes? Check your spam folder, or make sure you used the address you signed up with.</p>
<p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/auth/login']) ?>">Back to sign in</a></p>
