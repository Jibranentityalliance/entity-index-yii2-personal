<?php

namespace app\modules\portal\models;

use app\models\User;
use app\services\Mail;
use Yii;
use yii\base\Model;

class ForgotPasswordForm extends Model
{
    private const MAX_REQUESTS = 5;
    private const WINDOW = 900;
    private const RESEND_AFTER = 60;

    public $email;

    public function rules()
    {
        return [
            ['email', 'trim'],
            ['email', 'required'],
            ['email', 'email', 'enableIDN' => false],
            ['email', 'string', 'max' => 254],
            ['email', 'checkRate'],
        ];
    }

    public function checkRate($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }
        $key = 'portal.forgot.' . md5((string) Yii::$app->request->userIP);
        $count = (int) Yii::$app->cache->get($key);
        if ($count >= self::MAX_REQUESTS) {
            $this->addError($attribute, 'Too many reset requests. Try again in 15 minutes.');
            return;
        }
        Yii::$app->cache->set($key, $count + 1, self::WINDOW);
    }

    public function send(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $user = User::findByEmail((string) $this->email);
        if ($user !== null && !$user->passwordResetIssuedWithin(self::RESEND_AFTER)) {
            Mail::passwordReset($user, $user->issuePasswordReset());
        }
        return true;
    }
}
