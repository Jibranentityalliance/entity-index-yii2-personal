<?php

/** @var yii\web\View $this */
/** @var string $reason history | lookups */
/** @var array $free */

use app\helpers\Fmt;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = $reason === 'lookups' ? 'Daily lookup limit reached' : 'Older than your plan covers';
?>
<div class="card">
    <div class="card-body text-center py-5">
        <p class="mb-3"><i class="fas fa-lock fa-2x text-muted"></i></p>
        <?php if ($reason === 'lookups'): ?>
            <h3 class="h5 mb-2">You have used all <?= Fmt::num($free['lookupsPerDay']) ?> free lookups for today</h3>
            <p class="text-muted mb-4">Companies you already opened today stay available. New lookups reset at midnight New York time (<?= Html::encode((new \DateTimeImmutable('@' . $free['resetsAt']))->setTimezone(new \DateTimeZone(Yii::$app->params['reportTz']))->format('M j, g:i A')) ?>).</p>
        <?php else: ?>
            <h3 class="h5 mb-2">This company was filed before the last <?= (int) $free['historyDays'] ?> days</h3>
            <p class="text-muted mb-4">Free accounts can open companies filed <?= $free['floor'] ? 'from ' . Html::encode(Fmt::date($free['floor'])) . ' onward' : 'in the last ' . (int) $free['historyDays'] . ' days' ?>. Pro covers all history.</p>
        <?php endif ?>
        <a href="<?= Url::to(['/pricing/index', 'plan' => 'pro']) ?>" class="btn btn-primary">Upgrade to Pro</a>
        <a href="<?= Url::to(['/portal/companies/index']) ?>" class="btn btn-default ml-2">Back to Companies</a>
    </div>
</div>
