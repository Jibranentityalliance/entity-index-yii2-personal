<?php

namespace app\controllers;

use app\helpers\Fmt;
use app\helpers\States;
use app\services\EntityRepository;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class CompanyController extends Controller
{
    /** /company/{state}/{slug}-{urlkey}; the key (last segment) identifies the record, the slug is cosmetic. */
    public function actionView(string $state, string $slug)
    {
        $st = States::resolve($state);
        $e = $st ? EntityRepository::find($st['code'], Fmt::urlKeyFromSlug($slug)) : null;
        if (!$e) {
            throw new NotFoundHttpException('Company not found.');
        }
        // Canonicalize: /company/florida/old-name-123 -> /company/fl/current-name-123
        if ($state !== strtolower($st['code']) || $slug !== $e->slug . '-' . $e->url_key) {
            return $this->redirect(Fmt::entityUrl($e->attributes), 308);
        }
        return $this->render('view', ['e' => $e, 'st' => $st, 'related' => EntityRepository::related($e, 8)]);
    }
}
