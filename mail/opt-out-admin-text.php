New removal request #<?= (int) $request->id ?>


Name: <?= $request->person_name ?>

Email: <?= $request->email ?>

Company: <?= $request->entity_ref ?>

What to remove: <?= $request->reason ?: 'Not given' ?>


Review the request: <?= $link ?>
