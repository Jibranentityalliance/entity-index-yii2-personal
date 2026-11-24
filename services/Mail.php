<?php

namespace app\services;

use app\helpers\Fmt;
use app\helpers\States;
use app\models\Entity;
use app\models\OptOutRequest;
use app\models\SavedSearch;
use app\models\User;
use Yii;
use yii\helpers\Url;

class Mail
{
    public static function send(string $view, string $to, string $subject, array $params = [], array $headers = []): bool
    {
        $params += ['siteUrl' => Yii::$app->params['siteUrl'], 'subject' => $subject];
        try {
            $message = Yii::$app->mailer->compose(['html' => "$view-html", 'text' => "$view-text"], $params)
                ->setTo($to)
                ->setSubject($subject);
            foreach ($headers as $name => $value) {
                $message->setHeader($name, $value);
            }
            return $message->send();
        } catch (\Throwable $e) {
            Yii::error("Mail '$view' to $to failed: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    public static function passwordReset(User $user, string $token): bool
    {
        return self::send('password-reset', $user->email, 'Reset your EntityIndex password', [
            'name' => self::firstName($user),
            'link' => self::absolute(['/portal/auth/reset', 'token' => $token]),
            'minutes' => intdiv(User::RESET_TTL, 60),
        ]);
    }

    public static function passwordChanged(User $user): bool
    {
        return self::send('password-changed', $user->email, 'Your EntityIndex password was changed', [
            'name' => self::firstName($user),
            'when' => gmdate('j F Y, H:i') . ' UTC',
            'resetLink' => self::absolute(['/portal/auth/forgot']),
            'contactLink' => self::absolute(['/site/contact']),
        ]);
    }

    public static function optOutReceived(OptOutRequest $r): bool
    {
        return self::send('opt-out-received', $r->email, 'We received your removal request (#' . $r->id . ')', [
            'name' => self::first($r->person_name),
            'ref' => $r->id,
            'entityRef' => $r->entity_ref,
            'personName' => $r->person_name,
        ]);
    }

    public static function optOutForAdmin(OptOutRequest $r): bool
    {
        return self::send('opt-out-admin', (string) Yii::$app->params['adminEmail'], 'New removal request #' . $r->id . ' from ' . $r->person_name, [
            'request' => $r,
            'link' => self::absolute(['/admin/opt-outs/view', 'id' => $r->id]),
        ]);
    }

    public static function optOutDone(OptOutRequest $r, Entity $entity): bool
    {
        return self::send('opt-out-done', $r->email, 'Your details have been removed (#' . $r->id . ')', [
            'name' => self::first($r->person_name),
            'ref' => $r->id,
            'company' => $entity->name,
            'state' => States::name($entity->state),
            'filing' => $entity->state_filing_number,
        ]);
    }

    public static function optOutRejected(OptOutRequest $r): bool
    {
        return self::send('opt-out-rejected', $r->email, 'About your removal request (#' . $r->id . ')', [
            'name' => self::first($r->person_name),
            'ref' => $r->id,
            'entityRef' => $r->entity_ref,
            'formLink' => self::absolute(['/opt-out/index']),
        ]);
    }

    public static function savedSearchAlert(User $user, SavedSearch $search, int $total, array $items): bool
    {
        $unsubscribe = self::absolute(['/portal/alerts/unsubscribe', 'token' => $search->unsubscribe_token]);
        return self::send('saved-search', $user->email, Fmt::num($total) . ' new ' . ($total === 1 ? 'company' : 'companies') . ': ' . $search->name, [
            'name' => self::firstName($user),
            'search' => $search,
            'total' => $total,
            'items' => array_map(fn(array $e) => $e + ['link' => Yii::$app->params['siteUrl'] . Fmt::entityUrl($e)], $items),
            'searchLink' => self::absolute(['/portal/companies/index'] + $search->filters()),
            'manageLink' => self::absolute(['/portal/alerts/index']),
            'unsubscribeLink' => $unsubscribe,
        ], ['List-Unsubscribe' => '<' . $unsubscribe . '>']);
    }

    private static function absolute(array $route): string
    {
        return Yii::$app->params['siteUrl'] . Url::to($route);
    }

    private static function firstName(User $user): string
    {
        return self::first((string) $user->name);
    }

    private static function first(string $name): string
    {
        $name = trim($name);
        return $name === '' ? 'there' : explode(' ', $name)[0];
    }
}
