<?php

namespace app\modules\portal\models;

use app\models\User;
use yii\base\Model;

class RegisterForm extends Model
{
    public $name;
    public $email;
    public $password;
    public $password_repeat;

    public function rules()
    {
        return [
            [['name', 'email'], 'trim'],
            [['name', 'email', 'password', 'password_repeat'], 'required'],
            ['name', 'string', 'max' => 150],
            ['email', 'email', 'enableIDN' => false],
            ['email', 'string', 'max' => 254],
            ['email', 'unique', 'targetClass' => User::class, 'message' => 'An account with this email already exists.'],
            ['password', 'string', 'min' => 8, 'max' => 72, 'tooShort' => 'Use at least 8 characters.'],
            ['password_repeat', 'compare', 'compareAttribute' => 'password', 'message' => 'Passwords do not match.'],
        ];
    }

    public function attributeLabels()
    {
        return ['password_repeat' => 'Confirm password', 'name' => 'Full name'];
    }

    public function register(): ?User
    {
        if (!$this->validate()) {
            return null;
        }
        $user = new User(['email' => $this->email, 'name' => $this->name, 'role' => User::ROLE_CUSTOMER, 'plan' => 'free', 'status' => User::STATUS_ACTIVE]);
        $user->setPassword($this->password);
        return $user->save(false) ? $user : null;
    }
}
