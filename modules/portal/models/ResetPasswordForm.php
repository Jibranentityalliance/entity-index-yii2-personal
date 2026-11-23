<?php

namespace app\modules\portal\models;

use app\models\User;
use app\services\Mail;
use yii\base\Model;

class ResetPasswordForm extends Model
{
    public $password;
    public $password_repeat;

    public function __construct(private User $user, $config = [])
    {
        parent::__construct($config);
    }

    public function rules()
    {
        return [
            [['password', 'password_repeat'], 'required'],
            ['password', 'string', 'min' => 8, 'max' => 72, 'tooShort' => 'Use at least 8 characters.'],
            ['password_repeat', 'compare', 'compareAttribute' => 'password', 'message' => 'Passwords do not match.'],
        ];
    }

    public function attributeLabels()
    {
        return ['password' => 'New password', 'password_repeat' => 'Confirm new password'];
    }

    public function reset(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $this->user->changePassword($this->password);
        if (!$this->user->save(false)) {
            return false;
        }
        Mail::passwordChanged($this->user);
        return true;
    }
}
