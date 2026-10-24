<?php

/** @var yii\web\View $this */
/** @var array $stats */

use app\helpers\States;

$this->title = 'Coverage & freshness';
$live = count(array_intersect_key($stats, States::all()));
$within24 = count(array_filter($stats, fn($s) => ($s['medianLagHours'] ?? 99) <= 24));
?>
<p class="text-muted">Every state’s new filings come from official sources: open-data APIs and bulk files where they exist, the state website where they don’t.
    <span class="badge badge-success"><?= $live ?> of 51 jurisdictions live</span> <span class="badge badge-success"><?= $within24 ?> with median lag ≤ 24h</span></p>
<?= $this->render('_table', ['stats' => $stats, 'states' => States::all()]) ?>
