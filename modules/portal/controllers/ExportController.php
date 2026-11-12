<?php

namespace app\modules\portal\controllers;

use app\modules\apiv1\serializers\EntitySerializer;
use app\services\EntityRepository;
use app\services\ExportUsage;
use Yii;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class ExportController extends PortalController
{
    private const CHUNK = 1000;

    private const COLUMNS = [
        'Company', 'State', 'Filing number', 'Entity type', 'Status', 'Filed', 'County', 'Principal address', 'Mailing address',
        'Registered agent', 'Registered agent address', 'Registered agent phone', 'Officers', 'Officer phones', 'Industry (estimated)', 'Source URL',
    ];

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
        fputcsv($fp, self::COLUMNS, ',', '"', '');
        $written = 0;
        for ($offset = 0; $offset < $rows; $offset += self::CHUNK) {
            $batch = EntityRepository::filteredQuery($params)->with(['people', 'agent'])
                ->orderBy(['e.filed_at' => SORT_DESC, 'e.id' => SORT_DESC])->offset($offset)->limit(min(self::CHUNK, $rows - $offset))->all();
            if (!$batch) {
                break;
            }
            foreach ($batch as $e) {
                fputcsv($fp, $this->line(EntitySerializer::entity($e)), ',', '"', '');
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

    private function line(array $e): array
    {
        $agent = $e['registered_agent'];
        $officers = array_map(fn(array $p) => $p['name'] . ($p['title'] ? ' (' . $p['title'] . ')' : ''), $e['people']);
        $phones = array_map(fn(array $p) => $p['name'] . ': ' . $p['phone'], array_filter($e['people'], fn(array $p) => $p['phone'] !== null));
        return array_map([$this, 'safe'], [
            $e['name'], $e['state'], $e['filing_number'], $e['entity_type'], $e['status'],
            $e['filed_at'] ? substr($e['filed_at'], 0, 10) : null,
            $e['county'], $e['principal_address'], $e['mailing_address'],
            $agent['name'] ?? null, $agent['address']['full'] ?? null, $agent['phone'] ?? null,
            implode('; ', $officers), implode('; ', $phones),
            $e['industry']['label'] ?? null, $e['source']['url'],
        ]);
    }

    private function safe(?string $v): string
    {
        $v = (string) $v;
        return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
    }
}
