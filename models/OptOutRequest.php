<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $entity_ref
 * @property string $person_name
 * @property string $email
 * @property string|null $reason
 * @property string $status
 * @property string $created_at
 * @property string|null $resolved_at
 */
class OptOutRequest extends ActiveRecord
{
    public static function tableName()
    {
        return 'opt_out_requests';
    }

    public function rules()
    {
        return [
            [['entity_ref', 'person_name', 'email', 'reason'], 'trim'],
            [['entity_ref', 'person_name', 'email'], 'required'],
            ['email', 'email', 'enableIDN' => false],
            ['email', 'string', 'max' => 254],
            [['entity_ref'], 'string', 'max' => 500],
            ['person_name', 'string', 'max' => 255],
            ['reason', 'string', 'max' => 2000],
        ];
    }

    public function attributeLabels()
    {
        return ['entity_ref' => 'Company page URL or filing number', 'person_name' => 'Your name', 'email' => 'Email'];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = gmdate('Y-m-d H:i:s');
            $this->status = 'pending';
        }
        return parent::beforeSave($insert);
    }
}
