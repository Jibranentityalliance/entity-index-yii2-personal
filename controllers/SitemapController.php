<?php

namespace app\controllers;

use app\helpers\Fmt;
use app\helpers\States;
use app\services\EntityRepository;
use Yii;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * /sitemap.xml is a sitemap index pointing at /sitemap-company-{n}.xml (50,000 URLs each).
 * File 0 also carries the static pages and the 51 state pages. Submit /sitemap.xml in Search Console.
 */
class SitemapController extends Controller
{
    public function beforeAction($action)
    {
        $r = Yii::$app->response;
        $r->format = Response::FORMAT_RAW;
        $r->headers->set('Content-Type', 'application/xml; charset=utf-8');
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $per = Yii::$app->params['sitemapPageSize'];
        $now = gmdate('c');
        $n = max(1, (int) ceil(EntityRepository::totalCount() / $per));
        $idx = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $idx .= '  <sitemap><loc>' . Html::encode(Fmt::canonical('/sitemap-company-0.xml')) . "</loc><lastmod>$now</lastmod></sitemap>\n";
        for ($i = 1; $i < $n; $i++) {
            $idx .= '  <sitemap><loc>' . Html::encode(Fmt::canonical("/sitemap-company-$i.xml")) . "</loc><lastmod>$now</lastmod></sitemap>\n";
        }
        return $idx . "</sitemapindex>\n";
    }

    public function actionCompanies(int $page)
    {
        $per = Yii::$app->params['sitemapPageSize'];
        if ($page < 0 || $page > max(0, (int) ceil(EntityRepository::totalCount() / $per) - 1)) {
            throw new NotFoundHttpException();
        }
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        if ($page === 0) {
            // Static + state pages ride along in the first file.
            foreach (['/', '/new', '/states', '/pricing'] as $p) {
                $x .= '  <url><loc>' . Html::encode(Fmt::canonical($p)) . "</loc></url>\n";
            }
            foreach (States::all() as $s) {
                $x .= '  <url><loc>' . Html::encode(Fmt::canonical('/new/' . strtolower($s['code']))) . "</loc></url>\n";
            }
        }
        foreach (EntityRepository::sitemapPage($page * $per, $per) as $e) {
            $loc = Fmt::canonical('/company/' . strtolower($e['state']) . '/' . $e['slug'] . '-' . $e['url_key']);
            $x .= '  <url><loc>' . Html::encode($loc) . '</loc><lastmod>' . gmdate('c', strtotime($e['indexed_at'] . ' UTC')) . "</lastmod></url>\n";
        }
        return $x . "</urlset>\n";
    }
}
