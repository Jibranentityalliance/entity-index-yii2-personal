<?php

namespace app\modules\portal\models;

use app\models\User;
use Yii;
use yii\base\Model;

/** Email + password login with a simple lockout (5 failures per email+IP per 15 minutes). */
class LoginForm extends Model
{
    private const MAX_FAILURES = 5;
    private const WINDOW = 900;

    public $email;
    public $password;
    public $rememberMe = true;

    public function rules()
    {
        return [
            [['email', 'password'], 'required'],
            ['email', 'trim'],
            ['rememberMe', 'boolean'],
            ['password', 'validateCredentials'],
        ];
    }

    public function validateCredentials($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }
        $key = 'portal.login.' . md5(strtolower((string) $this->email) . '|' . Yii::$app->request->userIP);
        if ((int) Yii::$app->cache->get($key) >= self::MAX_FAILURES) {
            $this->addError($attribute, 'Too many failed attempts. Try again in 15 minutes.');
            return;
        }
        $user = User::findByEmail((string) $this->email);
        if (!$user || !$user->validatePassword((string) $this->password)) {
            Yii::$app->cache->set($key, (int) Yii::$app->cache->get($key) + 1, self::WINDOW);
            $this->addError($attribute, 'Incorrect email or password.');
        }
    }

    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $user = User::findByEmail((string) $this->email);
        $user->last_login_at = gmdate('Y-m-d H:i:s');
        $user->save(false, ['last_login_at', 'updated_at']);
        return Yii::$app->user->login($user, $this->rememberMe ? 3600 * 24 * 14 : 0);
    }
}
