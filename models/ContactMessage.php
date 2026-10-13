<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Public contact form message. `website` is a honeypot field: real visitors leave it empty.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $topic
 * @property string $message
 * @property string $status
 * @property string $created_at
 */
class ContactMessage extends ActiveRecord
{
    public const TOPICS = [
        'general' => 'General question',
        'sales' => 'Pricing / sales',
        'support' => 'Account support',
        'data' => 'Data license / bulk feed',
        'removal' => 'Data removal',
    ];

    public $website;

    public static function tableName()
    {
        return 'contact_messages';
    }

    public function rules()
    {
        return [
            [['name', 'email', 'topic', 'message', 'website'], 'trim'],
            [['name', 'email', 'message'], 'required'],
            ['name', 'string', 'max' => 150],
            ['email', 'email', 'enableIDN' => false],
            ['email', 'string', 'max' => 254],
            ['topic', 'in', 'range' => array_keys(self::TOPICS)],
            ['topic', 'default', 'value' => 'general'],
            ['message', 'string', 'min' => 10, 'max' => 5000, 'tooShort' => 'Please write at least 10 characters.'],
            ['website', 'safe'],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = gmdate('Y-m-d H:i:s');
            $this->status = 'new';
        }
        return parent::beforeSave($insert);
    }
}
