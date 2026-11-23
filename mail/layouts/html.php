<?php

use yii\helpers\Html;

$this->beginPage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::encode($message->getSubject()) ?></title>
    <?php $this->head() ?>
</head>
<body style="margin:0;padding:0;background:#f6f6f8;font-family:Inter,'Segoe UI',Helvetica,Arial,sans-serif;color:#3d4466;">
<?php $this->beginBody() ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f8;">
    <tr>
        <td align="center" style="padding:40px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                <tr>
                    <td style="padding:0 4px 20px;font-size:19px;font-weight:700;letter-spacing:-0.03em;color:#111a4a;">EntityIndex</td>
                </tr>
                <tr>
                    <td style="background:#ffffff;border:1px solid #e4e5ec;border-radius:8px;padding:32px;font-size:15px;line-height:1.6;color:#3d4466;">
                        <?= $content ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 4px 0;font-size:12px;line-height:1.6;color:#9a9db0;">
                        You received this email because of activity on your EntityIndex account. EntityIndex, business formation data from US state registries.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
