<?php

namespace app\controllers;

use app\helpers\Fmt;
use Yii;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\Response;

/** /sitemap.xml: the public marketing and legal pages (the product lives behind login). */
class SitemapController extends Controller
{
    private const PAGES = [
        ['/', 'weekly', '1.0'], ['/features', 'monthly', '0.8'], ['/pricing', 'monthly', '0.8'], ['/faq', 'monthly', '0.6'],
        ['/about', 'yearly', '0.5'], ['/contact', 'yearly', '0.5'], ['/legal/terms', 'yearly', '0.3'], ['/legal/privacy', 'yearly', '0.3'],
    ];

    public function beforeAction($action)
    {
        $r = Yii::$app->response;
        $r->format = Response::FORMAT_RAW;
        $r->headers->set('Content-Type', 'application/xml; charset=utf-8');
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::PAGES as [$path, $freq, $prio]) {
            $x .= '  <url><loc>' . Html::encode(Fmt::canonical($path)) . "</loc><changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
        }
        return $x . "</urlset>\n";
    }
}
