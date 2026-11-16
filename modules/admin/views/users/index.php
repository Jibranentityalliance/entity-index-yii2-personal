<?php

/** @var yii\web\View $this */
/** @var array[] $items */
/** @var int $total */
/** @var int $page */
/** @var int $pageSize */
/** @var array{q:string,plan:string,role:string,status:string} $filters */

use app\helpers\Fmt;
use app\modules\admin\models\UserForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Users';
$select = function (string $name, string $all, array $options) use ($filters): string {
    $html = '<option value="">' . Html::encode($all) . '</option>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . Html::encode($value) . '"' . ((string) $filters[$name] === (string) $value ? ' selected' : '') . '>' . Html::encode($label) . '</option>';
    }
    return '<label for="us-' . $name . '" class="sr-only">' . Html::encode($all) . '</label><select id="us-' . $name . '" class="form-control" name="' . $name . '">' . $html . '</select>';
};
$filtered = (bool) array_filter($filters, fn($v) => $v !== '');
?>
<div class="card">
    <form action="<?= Url::to(['/admin/users/index']) ?>" class="card-body ei-filters">
        <div class="form-row align-items-center">
            <div class="col-sm-6 col-lg">
                <label for="us-q" class="sr-only">Name or email</label>
                <input id="us-q" class="form-control" name="q" type="search" value="<?= Html::encode($filters['q']) ?>" placeholder="Name or email">
            </div>
            <div class="col-sm-6 col-lg"><?= $select('plan', 'Any plan', UserForm::PLANS) ?></div>
            <div class="col-sm-6 col-lg"><?= $select('role', 'Any role', UserForm::ROLES) ?></div>
            <div class="col-sm-6 col-lg"><?= $select('status', 'Any status', UserForm::STATUSES) ?></div>
            <div class="col-sm-6 col-lg-auto d-flex">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($filtered): ?><a href="<?= Url::to(['/admin/users/index']) ?>" class="btn btn-default ml-2">Reset</a><?php endif ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (!$items): ?>
        <div class="p-4 text-center text-muted">No users match.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Joined</th>
                    <th class="d-none d-md-table-cell">Last sign-in</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $u): ?>
                    <tr>
                        <td>
                            <a class="font-weight-bold" href="<?= Url::to(['/admin/users/update', 'id' => $u['id']]) ?>"><?= Html::encode($u['email']) ?></a>
                            <?php if ($u['name']): ?><div class="text-muted small"><?= Html::encode($u['name']) ?></div><?php endif ?>
                        </td>
                        <td>
                            <?= Html::encode(UserForm::PLANS[$u['plan']] ?? $u['plan']) ?>
                            <?php if ($u['limit_overrides']): ?><span class="badge badge-light ml-1" title="Has custom limits">Custom limits</span><?php endif ?>
                        </td>
                        <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-success' : 'badge-secondary' ?>"><?= Html::encode(UserForm::ROLES[$u['role']] ?? $u['role']) ?></span></td>
                        <td><span class="badge <?= (int) $u['status'] === 10 ? 'badge-success' : 'badge-danger' ?>"><?= (int) $u['status'] === 10 ? 'Active' : 'Disabled' ?></span></td>
                        <td class="d-none d-md-table-cell text-muted text-nowrap"><?= Html::encode(Fmt::date($u['created_at'])) ?></td>
                        <td class="d-none d-md-table-cell text-muted text-nowrap"><?= Html::encode($u['last_login_at'] ? Fmt::dateTime($u['last_login_at']) : 'Never') ?></td>
                        <td class="text-right"><a class="btn btn-default btn-sm" href="<?= Url::to(['/admin/users/update', 'id' => $u['id']]) ?>">Manage</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@app/modules/portal/views/_partials/pager', [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'hrefFor' => fn(int $p) => Url::to(['/admin/users/index', 'page' => $p] + array_filter($filters, fn($v) => $v !== '')),
        ]) ?>
    <?php endif ?>
</div>
