<?php

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $entity_id @property string $filing_type @property string $filing_date @property string|null $document_url */
class Filing extends ActiveRecord
{
    public static function tableName()
    {
        return 'filings';
    }
}
