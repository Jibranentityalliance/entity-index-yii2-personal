<?php

namespace app\modules\portal\controllers;

use app\modules\apiv1\serializers\EntitySerializer;
use app\services\CompanyCsv;
use app\services\EntityRepository;
use app\services\ExportUsage;
use Yii;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class ExportController extends PortalController
{
    private const CHUNK = 1000;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => ['companies' => ['post']]];
        return $behaviors;
    }

    public function actionCompanies()
    {
        $user = Yii::$app->user->identity;
        if (!$user->hasExportAccess()) {
            throw new ForbiddenHttpException('Your plan does not include CSV export.');
        }
        $params = $this->companyFilters(Yii::$app->request->post());
        $allowance = ExportUsage::allowance($user);
        $total = (int) EntityRepository::filteredQuery($params)->select('COUNT(*)')->orderBy(null)->scalar();
        $back = ['/portal/companies/index'] + array_filter($params);

        if ($total === 0) {
            return $this->refuse('No companies match these filters, so there is nothing to export.', $back);
        }
        if ($allowance < 1) {
            return $this->refuse('You have used all of this month\'s export rows. The allowance resets on the 1st (UTC).', $back);
        }

        $rows = min($total, $allowance);
        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, CompanyCsv::COLUMNS, ',', '"', '');
        $written = 0;
        for ($offset = 0; $offset < $rows; $offset += self::CHUNK) {
            $batch = EntityRepository::filteredQuery($params)->with(['people', 'agent'])
                ->orderBy(['e.filed_at' => SORT_DESC, 'e.id' => SORT_DESC])->offset($offset)->limit(min(self::CHUNK, $rows - $offset))->all();
            if (!$batch) {
                break;
            }
            foreach ($batch as $e) {
                fputcsv($fp, CompanyCsv::line(EntitySerializer::entity($e)), ',', '"', '');
                $written++;
            }
        }
        ExportUsage::add($user, $written);
        if (Yii::$app->request->isAjax) {
            Yii::$app->session->setFlash('success', 'Exported ' . number_format($written) . ($written === 1 ? ' row.' : ' rows.'));
        }
        rewind($fp);
        return Yii::$app->response->sendStreamAsFile($fp, 'entityindex-companies-' . gmdate('Y-m-d-His') . '.csv', ['mimeType' => 'text/csv; charset=UTF-8']);
    }

    private function refuse(string $message, array $back)
    {
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->statusCode = 422;
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['error' => $message];
        }
        Yii::$app->session->setFlash('error', $message);
        return $this->redirect($back);
    }
}
