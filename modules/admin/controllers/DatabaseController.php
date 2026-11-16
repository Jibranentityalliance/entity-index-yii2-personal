<?php

namespace app\modules\admin\controllers;

use app\services\AdminStats;
use Yii;
use yii\filters\VerbFilter;

class DatabaseController extends AdminController
{
    private const PAGE_SIZE = 50;
    private const MAX_IDS = 500;
    private const SHOWS = [AdminStats::COOLDOWN, AdminStats::HELD, AdminStats::PUBLISHED];

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => ['publish' => ['post']]];
        return $behaviors;
    }

    public function actionIndex(string $show = AdminStats::COOLDOWN, int $page = 1, int $refresh = 0, string $q = '')
    {
        $show = in_array($show, self::SHOWS, true) ? $show : AdminStats::COOLDOWN;
        $q = trim($q);
        $stats = new AdminStats(Yii::$app->db);

        $summary = $refresh ? null : Yii::$app->cache->get(AdminStats::CACHE_KEY);
        if (!is_array($summary)) {
            $summary = $stats->summary();
            Yii::$app->cache->set(AdminStats::CACHE_KEY, $summary, AdminStats::CACHE_TTL);
        }

        $total = $show === AdminStats::PUBLISHED && $q === '' ? $summary['published'] : $stats->count($show, null, $q);
        $page = min(max(1, $page), max(1, (int) ceil($total / self::PAGE_SIZE)));

        return $this->render('index', [
            'summary' => $summary,
            'items' => $stats->list($show, $page, self::PAGE_SIZE, $q),
            'show' => $show,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'total' => $total,
            'q' => $q,
        ]);
    }

    public function actionPublish()
    {
        $req = Yii::$app->request;
        $ids = $req->post('ids');
        $mode = (string) $req->post('mode');
        $show = (string) $req->post('show', AdminStats::COOLDOWN);
        $back = ['index', 'show' => $show, 'page' => max(1, (int) $req->post('page', 1)), 'q' => (string) $req->post('q', '')];
        if (!is_array($ids) || !$ids || !in_array($mode, ['release', 'hold'], true)) {
            Yii::$app->session->setFlash('error', 'Select at least one company.');
            return $this->redirect($back);
        }
        if (count($ids) > self::MAX_IDS) {
            Yii::$app->session->setFlash('error', 'Select at most ' . self::MAX_IDS . ' companies at a time.');
            return $this->redirect($back);
        }
        $n = (new AdminStats(Yii::$app->db))->setPublish($ids, $mode, (int) Yii::$app->user->id);
        Yii::$app->cache->delete(AdminStats::CACHE_KEY);
        Yii::$app->session->setFlash('success', ($mode === 'release' ? 'Released ' : 'Held ') . number_format($n) . ($n === 1 ? ' company.' : ' companies.')
            . ($mode === 'release' ? ' They are public now.' : ' They are hidden until an admin releases them.'));
        return $this->redirect($back);
    }
}
