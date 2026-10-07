<?php

/** @var yii\web\View $this */
/** @var int $page */
/** @var int $pageSize */
/** @var int $total */
/** @var callable $hrefFor fn(int $page): string */

use app\helpers\Fmt;

if ($total === 0) {
    return;
}
$pages = max(1, (int) ceil($total / $pageSize));
$from = ($page - 1) * $pageSize + 1;
$to = min($total, $page * $pageSize);
?>
<div class="pager d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="text-muted"><?= Fmt::num($from) ?>–<?= Fmt::num($to) ?> of <?= Fmt::num($total) ?></span>
    <span class="d-flex align-items-center gap-2">
        <?php if ($page > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $hrefFor($page - 1) ?>" rel="prev">← Prev</a><?php endif ?>
        <span class="text-muted">Page <?= Fmt::num($page) ?> of <?= Fmt::num($pages) ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $hrefFor($page + 1) ?>" rel="next">Next →</a><?php endif ?>
    </span>
</div>
