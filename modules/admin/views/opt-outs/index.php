<?php

/** @var yii\web\View $this */
/** @var array[] $items */
/** @var string $status */
/** @var array<string,int|string> $counts */
/** @var int $total */
/** @var int $page */
/** @var int $pageSize */

use app\helpers\Fmt;
use app\modules\admin\controllers\OptOutsController;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Opt-out requests';
$tabs = OptOutsController::STATUSES + ['all' => 'All'];
$count = fn(string $key) => $key === 'all' ? array_sum($counts) : (int) ($counts[$key] ?? 0);
$badge = ['pending' => 'badge-warning', 'done' => 'badge-success', 'rejected' => 'badge-secondary'];
?>
<p class="text-muted">People who asked, through the public form, to have their details removed. Approving a request hides every officer, the registered agent and the addresses on the company they point to, in the portal, search, API, CSV export and bulk files.</p>

<div class="d-flex flex-wrap mb-2">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="btn btn-sm mr-2 mb-2 <?= $status === $key ? 'btn-primary' : 'btn-default' ?>" href="<?= Url::to(['/admin/opt-outs/index', 'status' => $key]) ?>"><?= Html::encode($label) ?> <span class="badge badge-light ml-1"><?= Fmt::num($count($key)) ?></span></a>
    <?php endforeach ?>
</div>

<div class="card">
    <?php if (!$items): ?>
        <div class="p-4 text-center text-muted"><?= $status === 'pending' ? 'No requests are waiting.' : 'No requests here.' ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Received</th>
                    <th>Name</th>
                    <th class="d-none d-md-table-cell">Company reference</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $r): ?>
                    <tr>
                        <td class="text-nowrap"><?= Html::encode(Fmt::dateTime($r['created_at'])) ?></td>
                        <td><span class="font-weight-bold"><?= Html::encode($r['person_name']) ?></span><div class="text-muted small"><?= Html::encode($r['email']) ?></div></td>
                        <td class="d-none d-md-table-cell text-muted"><?= Html::encode(mb_strimwidth($r['entity_ref'], 0, 70, '…')) ?></td>
                        <td><span class="badge <?= $badge[$r['status']] ?? 'badge-secondary' ?>"><?= Html::encode(OptOutsController::STATUSES[$r['status']] ?? $r['status']) ?></span></td>
                        <td class="text-right"><a class="btn btn-default btn-sm" href="<?= Url::to(['/admin/opt-outs/view', 'id' => $r['id']]) ?>"><?= $r['status'] === 'pending' ? 'Review' : 'View' ?></a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@app/modules/portal/views/_partials/pager', [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'hrefFor' => fn(int $p) => Url::to(['/admin/opt-outs/index', 'status' => $status, 'page' => $p]),
        ]) ?>
    <?php endif ?>
</div>
