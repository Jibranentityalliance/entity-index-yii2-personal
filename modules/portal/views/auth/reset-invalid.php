<?php

use yii\helpers\Url;

$this->title = 'Link expired';
?>
<p class="login-box-msg">This reset link has expired or was already used</p>
<p>Reset links work once, for one hour. Request a new one and use the most recent email we send you.</p>
<a href="<?= Url::to(['/portal/auth/forgot']) ?>" class="btn btn-primary btn-block">Request a new link</a>
<p class="mt-3 mb-0"><a href="<?= Url::to(['/portal/auth/login']) ?>">Back to sign in</a></p>
