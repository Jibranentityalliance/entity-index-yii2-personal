<?php

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var string $theme light | dark | system */

use yii\helpers\Html;
use yii\helpers\Url;

$display = trim((string) $user->name) !== '' ? trim($user->name) : $user->email;
$initial = mb_strtoupper(mb_substr($display, 0, 1));
?>
<div class="dropdown ei-user-menu">
    <button type="button" class="btn ei-avatar" id="ei-user-menu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Account menu" title="<?= Html::encode($display) ?>"><?= Html::encode($initial) ?></button>
    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="ei-user-menu">
        <div class="ei-user-menu-head">
            <span class="ei-avatar ei-avatar-sm" aria-hidden="true"><?= Html::encode($initial) ?></span>
            <span class="ei-user-menu-who">
                <?php if (trim((string) $user->name) !== ''): ?><span class="ei-user-menu-name"><?= Html::encode($user->name) ?></span><?php endif ?>
                <span class="ei-user-menu-email"><?= Html::encode($user->email) ?></span>
            </span>
        </div>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?= Url::to(['/portal/account/index']) ?>"><i class="fas fa-user-cog"></i> Account settings</a>
        <div class="dropdown-divider"></div>
        <div class="ei-user-menu-theme">
            <span class="ei-user-menu-label">Theme</span>
            <div class="ei-segmented" role="group" aria-label="Theme">
                <?php foreach (['light' => ['Light', 'fa-sun'], 'dark' => ['Dark', 'fa-moon'], 'system' => ['System', 'fa-desktop']] as $value => [$label, $icon]): ?>
                    <button type="button" class="<?= $theme === $value ? 'active' : '' ?>" data-ei-theme-set="<?= $value ?>" aria-pressed="<?= $theme === $value ? 'true' : 'false' ?>"><i class="fas <?= $icon ?>"></i> <?= $label ?></button>
                <?php endforeach ?>
            </div>
        </div>
        <div class="dropdown-divider"></div>
        <?= Html::beginForm(['/portal/auth/logout'], 'post') ?>
        <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Sign out</button>
        <?= Html::endForm() ?>
    </div>
</div>
