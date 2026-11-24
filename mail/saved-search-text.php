<?php

use app\helpers\Fmt;
use app\helpers\States;

$more = $total - count($items);
?>
Hi <?= $name ?>,

<?= Fmt::num($total) ?> new <?= $total === 1 ? 'company' : 'companies' ?> for "<?= $search->name ?>" (<?= $search->summary() ?>) went public since your last alert<?= $more > 0 ? '. The ' . count($items) . ' most recent filings' : '' ?>:

<?php foreach ($items as $e): ?>
- <?= $e['name'] ?>

  <?= implode(' · ', array_filter([$e['entity_type'], $e['county'] ? $e['county'] . ' County' : null, States::name($e['state']) ?: $e['state'], 'filed ' . Fmt::date($e['filed_at'])])) ?>

  <?= $e['link'] ?>


<?php endforeach ?>
<?= $more > 0 ? 'See all ' . Fmt::num($total) : 'Open this search' ?>: <?= $searchLink ?>


Manage alerts: <?= $manageLink ?>

Stop emails for this search: <?= $unsubscribeLink ?>
