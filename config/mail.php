<?php

$mailDsn = env('MAIL_DSN');

return [
    'class' => \yii\symfonymailer\Mailer::class,
    'viewPath' => '@app/mail',
    'htmlLayout' => 'layouts/html',
    'textLayout' => 'layouts/text',
    'useFileTransport' => $mailDsn === null,
    'fileTransportPath' => '@runtime/mail',
    'messageConfig' => ['from' => [(string) env('ADMIN_EMAIL', 'no-reply@localhost') => 'EntityIndex']],
] + ($mailDsn === null ? [] : ['transport' => ['dsn' => (string) $mailDsn]]);
