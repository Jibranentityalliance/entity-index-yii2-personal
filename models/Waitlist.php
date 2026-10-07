<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Waitlist signup. `website` is a honeypot field: real visitors leave it empty.
 *
 * @property int $id
 * @property string $email
 * @property string|null $use_case
 * @property string $created_at
 */
class Waitlist extends ActiveRecord
{
    public $plan;
    public $website;

    public static function tableName()
    {
        return 'waitlist';
    }

    public function rules()
    {
        return [
            [['email', 'use_case', 'plan', 'website'], 'trim'],
            ['email', 'required', 'message' => 'Enter a valid email address.'],
            ['email', 'email', 'enableIDN' => false, 'message' => 'Enter a valid email address.'],
            ['email', 'string', 'max' => 254],
            [['use_case', 'plan'], 'string', 'max' => 200],
            ['website', 'safe'],
        ];
    }

    /** Saves the signup; an existing email just gets its use case updated. */
    public function subscribe(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $useCase = trim(implode(' · ', array_filter([$this->plan, $this->use_case]))) ?: null;
        $row = static::findOne(['email' => $this->email]);
        if ($row === null) {
            $row = new static(['email' => $this->email]);
            $row->created_at = gmdate('Y-m-d H:i:s');
        }
        if ($useCase !== null) {
            $row->use_case = $useCase;
        }
        return $row->save(false);
    }
}
