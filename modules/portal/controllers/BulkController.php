<?php

namespace app\modules\portal\controllers;

use app\services\bulk\Bulk;
use app\services\bulk\BulkCatalog;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class BulkController extends PortalController
{
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!Yii::$app->user->identity->hasBulkAccess()) {
            throw new ForbiddenHttpException('Your plan does not include bulk downloads.');
        }
        return true;
    }

    public function actionIndex(string $date = '')
    {
        $user = Yii::$app->user->identity;
        $catalog = new BulkCatalog(Yii::$app->db);
        $dates = $catalog->dates();
        $date = in_array($date, $dates, true) ? $date : ($dates[0] ?? null);
        $cap = $this->cap($user);
        return $this->render('index', [
            'dates' => $dates,
            'date' => $date,
            'files' => $date === null ? [] : $catalog->files($date),
            'cap' => $cap,
            'usedToday' => $catalog->downloadsToday($user->id),
        ]);
    }

    private function cap(\app\models\User $user): ?int
    {
        return $user->isAdmin() ? null : (int) $user->withOverrides('bulk', Bulk::config())['downloadsPerDay'];
    }

    public function actionDownload(int $id)
    {
        $user = Yii::$app->user->identity;
        $catalog = new BulkCatalog(Yii::$app->db);
        $file = $catalog->file($id);
        if ($file === null) {
            throw new NotFoundHttpException('File not found.');
        }
        $range = Yii::$app->request->headers->get('Range');
        $resumed = $range !== null && !str_starts_with($range, 'bytes=0-');
        $cap = $this->cap($user);
        if (!$resumed && $cap !== null && $catalog->downloadsToday($user->id) >= (int) $cap) {
            Yii::$app->session->setFlash('error', 'You have reached today\'s limit of ' . number_format((int) $cap) . ' downloads. It resets at midnight UTC.');
            return $this->redirect(['index', 'date' => $file['build_date']]);
        }
        $response = Bulk::store()->send($file['file_key'], $file['file_name']);
        if (!$resumed) {
            $catalog->logDownload($user->id, $file);
        }
        Yii::$app->session->close();
        return $response;
    }
}
