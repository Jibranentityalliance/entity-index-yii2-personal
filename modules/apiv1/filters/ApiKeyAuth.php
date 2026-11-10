<?php

namespace app\modules\apiv1\filters;

use app\models\ApiKey;
use app\models\User;
use yii\filters\auth\AuthMethod;
use yii\web\ForbiddenHttpException;

class ApiKeyAuth extends AuthMethod
{
    public ?ApiKey $apiKey = null;

    public function authenticate($user, $request, $response)
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return null;
        }
        $key = ApiKey::findActiveByToken($token);
        $owner = $key ? User::findIdentity($key->user_id) : null;
        if ($key === null || $owner === null) {
            return null;
        }
        if (!$owner->hasApiAccess()) {
            throw new ForbiddenHttpException('Your plan does not include API access.');
        }
        $key->touchUsed();
        $user->setIdentity($owner);
        $this->apiKey = $key;
        $this->owner->apiKey = $key;
        return $owner;
    }

    public function challenge($response)
    {
        $response->getHeaders()->set('WWW-Authenticate', 'Bearer realm="EntityIndex API"');
    }

    private function extractToken($request): ?string
    {
        $header = $request->getHeaders()->get('Authorization');
        if ($header !== null && preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
            return $m[1];
        }
        $alt = $request->getHeaders()->get('X-API-Key');
        return $alt !== null && trim($alt) !== '' ? trim($alt) : null;
    }
}
