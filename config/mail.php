<?php

return [
    'class' => \yii\symfonymailer\Mailer::class,
    'viewPath' => '@app/mail',
    'htmlLayout' => 'layouts/html',
    'textLayout' => 'layouts/text',
    'useFileTransport' => true,
    'fileTransportPath' => '@runtime/mail',
    'messageConfig' => ['from' => [(string) env('ADMIN_EMAIL', 'no-reply@localhost') => 'EntityIndex']],
];
