<?php

/** @var yii\web\View $this */
/** @var array $faq */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Frequently asked questions';
$this->params['description'] = 'Answers about EntityIndex data sources, freshness, accounts, billing, licensing and privacy.';
$this->params['canonical'] = '/faq';
$groups = [];
foreach ($faq as [$cat, $q, $a]) {
    $groups[$cat][] = [$q, $a];
}
?>
<div class="container prose">
    <div class="page-head"><h1>Frequently asked questions</h1></div>
    <?php foreach ($groups as $cat => $items): ?>
        <h2 class="h4 mt-4"><?= Html::encode($cat) ?></h2>
        <div class="faq">
            <?php foreach ($items as [$q, $a]): ?>
                <details><summary><?= Html::encode($q) ?></summary><p class="text-muted"><?= Html::encode($a) ?><?= str_contains($q, 'remove my personal') ? ' <a href="' . Url::to(['/opt-out/index']) . '">Request removal</a>.' : '' ?></p></details>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
    <p class="mt-4 text-muted">Still have a question? <a href="<?= Url::to(['/site/contact']) ?>">Contact us</a>.</p>
</div>
