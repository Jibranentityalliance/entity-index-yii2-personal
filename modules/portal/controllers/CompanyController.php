<?php

namespace app\modules\portal\controllers;

use app\helpers\Fmt;
use app\helpers\States;
use app\services\EntityRepository;
use app\services\FreePlan;
use Yii;
use yii\web\NotFoundHttpException;

class CompanyController extends PortalController
{
    /** /portal/company/{state}/{slug}-{urlkey}; the key (last segment) identifies the record, the slug is cosmetic. */
    public function actionView(string $state, string $slug)
    {
        // 'na': companies converted from source rows without a valid state.
        $st = strtolower($state) === 'na' ? ['code' => 'NA', 'name' => 'N/A', 'slug' => 'na'] : States::resolve($state);
        $e = $st ? EntityRepository::find($st['code'], Fmt::urlKeyFromSlug($slug)) : null;
        if (!$e) {
            throw new NotFoundHttpException('Company not found.');
        }
        // Canonicalize: /company/florida/old-name-123 -> /company/fl/current-name-123
        if ($state !== strtolower($st['code']) || $slug !== $e->slug . '-' . $e->url_key) {
            return $this->redirect(Fmt::entityUrl($e->attributes), 308);
        }
        $user = Yii::$app->user->identity;
        $free = FreePlan::summary($user);
        if ($free !== null) {
            if (!FreePlan::inHistory($user, $e->filed_at)) {
                return $this->restricted('history', $free, 403);
            }
            if (!FreePlan::lookup($user, (int) $e->id)) {
                return $this->restricted('lookups', $free, 429);
            }
            $free = FreePlan::summary($user);
        }
        return $this->render('view', [
            'e' => $e, 'st' => $st, 'related' => EntityRepository::related($e, 8),
            'full' => $free === null, 'free' => $free,
        ]);
    }

    private function restricted(string $reason, array $free, int $status)
    {
        Yii::$app->response->statusCode = $status;
        return $this->render('restricted', ['reason' => $reason, 'free' => $free]);
    }
}
