<?php

namespace app\modules\portal\models;

use app\models\User;
use app\services\Mail;
use yii\base\Model;

/** Edit name; optionally change password (requires the current one). */
class AccountForm extends Model
{
    public $name;
    public $current_password;
    public $new_password;
    public $new_password_repeat;

    private User $user;

    public function __construct(User $user, $config = [])
    {
        $this->user = $user;
        $this->name = $user->name;
        parent::__construct($config);
    }

    public function rules()
    {
        return [
            ['name', 'trim'],
            ['name', 'required'],
            ['name', 'string', 'max' => 150],
            ['new_password', 'string', 'min' => 8, 'max' => 72, 'tooShort' => 'Use at least 8 characters.'],
            ['new_password_repeat', 'compare', 'compareAttribute' => 'new_password', 'message' => 'Passwords do not match.'],
            ['current_password', 'required', 'when' => fn() => (string) $this->new_password !== '', 'whenClient' => 'function(){return false;}'],
            ['current_password', 'validateCurrent', 'skipOnEmpty' => false],
        ];
    }

    public function attributeLabels()
    {
        return ['new_password' => 'New password', 'new_password_repeat' => 'Confirm new password', 'current_password' => 'Current password'];
    }

    public function validateCurrent($attribute)
    {
        if ((string) $this->new_password !== '' && !$this->hasErrors($attribute) && !$this->user->validatePassword((string) $this->current_password)) {
            $this->addError($attribute, 'Current password is incorrect.');
        }
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $this->user->name = $this->name;
        $changed = (string) $this->new_password !== '';
        if ($changed) {
            $this->user->changePassword($this->new_password);
        }
        if (!$this->user->save(false)) {
            return false;
        }
        if ($changed) {
            Mail::passwordChanged($this->user);
        }
        return true;
    }
}
