<?php

namespace app\modules\admin\models;

use app\models\User;
use yii\base\Model;

class UserForm extends Model
{
    public const PLANS = ['free' => 'Free', 'pro' => 'Pro', 'business' => 'Business / API', 'bulk' => 'Data license'];
    public const ROLES = ['customer' => 'Customer', 'admin' => 'Admin'];
    public const STATUSES = [User::STATUS_ACTIVE => 'Active', User::STATUS_DISABLED => 'Disabled'];
    public const LIMITS = [
        'api' => ['title' => 'REST API (Business, Data license)', 'fields' => [
            'monthlyCalls' => 'Calls a month', 'monthlyRows' => 'Companies returned a month', 'perMinute' => 'Calls a minute']],
        'export' => ['title' => 'CSV export (Pro and above)', 'fields' => [
            'monthlyRows' => 'Rows a month', 'perFile' => 'Rows in one file']],
        'free' => ['title' => 'Free plan', 'fields' => [
            'lookupsPerDay' => 'Company lookups a day', 'historyDays' => 'Days of company details']],
        'bulk' => ['title' => 'Bulk data (Data license)', 'fields' => [
            'downloadsPerDay' => 'Downloads a day']],
    ];
    private const MAX = 2000000000;

    public $name;
    public $plan;
    public $role;
    public $status;
    public $limits = [];

    public function rules()
    {
        return [
            ['name', 'trim'],
            ['name', 'string', 'max' => 150],
            [['plan', 'role', 'status'], 'required'],
            ['plan', 'in', 'range' => array_keys(self::PLANS)],
            ['role', 'in', 'range' => array_keys(self::ROLES)],
            ['status', 'in', 'range' => array_map('strval', array_keys(self::STATUSES))],
            ['limits', 'safe'],
            ['limits', 'validateLimits'],
        ];
    }

    public function validateLimits($attribute)
    {
        foreach (self::LIMITS as $group => $def) {
            foreach ($def['fields'] as $key => $label) {
                $v = $this->limitValue($group, $key);
                if ($v === '') {
                    continue;
                }
                if ($group === 'export' && $key === 'monthlyRows' && strtolower($v) === 'unlimited') {
                    continue;
                }
                if (preg_match('/^\d{1,10}$/', $v) !== 1 || (int) $v > self::MAX) {
                    $this->addError($attribute, $label . ': enter a whole number' . ($group === 'export' && $key === 'monthlyRows' ? ', "unlimited"' : '') . ' or leave it empty.');
                }
            }
        }
    }

    public static function fromUser(User $user): self
    {
        $f = new self();
        $f->name = $user->name;
        $f->plan = $user->plan;
        $f->role = $user->role;
        $f->status = (string) $user->status;
        foreach ($user->limitOverrides() as $group => $values) {
            foreach ((array) $values as $key => $v) {
                $f->limits[$group][$key] = $v === null ? 'unlimited' : (string) $v;
            }
        }
        return $f;
    }

    public function limitValue(string $group, string $key): string
    {
        $v = $this->limits[$group][$key] ?? '';
        return is_scalar($v) ? trim((string) $v) : '';
    }

    public function apply(User $user): void
    {
        $user->name = $this->name === '' ? null : $this->name;
        $user->plan = $this->plan;
        $user->role = $this->role;
        $user->status = (int) $this->status;
        $overrides = [];
        foreach (self::LIMITS as $group => $def) {
            foreach ($def['fields'] as $key => $label) {
                $v = $this->limitValue($group, $key);
                if ($v !== '') {
                    $overrides[$group][$key] = strtolower($v) === 'unlimited' ? null : (int) $v;
                }
            }
        }
        $user->limit_overrides = $overrides ? json_encode($overrides, JSON_THROW_ON_ERROR) : null;
    }
}
