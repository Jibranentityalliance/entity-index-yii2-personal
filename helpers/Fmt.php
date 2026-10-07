<?php

namespace app\helpers;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

/** Formatting and URL helpers shared by views. DB datetimes are UTC strings ("Y-m-d H:i:s"). */
class Fmt
{
    private static function utc(string $v): DateTimeImmutable
    {
        return new DateTimeImmutable(strlen($v) === 10 ? $v . ' 12:00:00' : $v, new DateTimeZone('UTC'));
    }

    public static function date(?string $v): string
    {
        return $v ? self::utc($v)->format('M j, Y') : '—';
    }

    /** Date without the year, e.g. "Sep 28". */
    public static function dayShort(string $day): string
    {
        return self::utc($day)->format('M j');
    }

    public static function dateTime(?string $v): string
    {
        return $v ? self::utc($v)->setTimezone(new DateTimeZone('America/New_York'))->format('M j, Y, g:i A T') : '—';
    }

    public static function dayLong(string $day): string
    {
        return self::utc($day)->format('l, F j, Y');
    }

    public static function num($n): string
    {
        return number_format((float) $n);
    }

    public static function lagHours(string $filedAt, string $indexedAt): float
    {
        return (self::utc($indexedAt)->getTimestamp() - self::utc($filedAt)->getTimestamp()) / 3600;
    }

    public static function lag(float $hours): string
    {
        if ($hours < 1) {
            return max(1, (int) round($hours * 60)) . ' min';
        }
        if ($hours < 48) {
            return round($hours) . 'h';
        }
        return round($hours / 24) . 'd';
    }

    public static function titleCase(string $s): string
    {
        return ucwords(str_replace('_', ' ', $s));
    }

    /** "garcia-exteriors-llc-7008089" -> "7008089" (url_key is always the last segment). */
    public static function urlKeyFromSlug(string $slug): string
    {
        $i = strrpos($slug, '-');
        return strtolower($i === false ? $slug : substr($slug, $i + 1));
    }

    public static function entityUrl(array $e, bool $absolute = false): string
    {
        return Url::to(['/company/view', 'state' => strtolower($e['state']), 'slug' => $e['slug'] . '-' . $e['url_key']], $absolute);
    }

    public static function stateUrl(string $code): string
    {
        return Url::to(['/new/state', 'state' => strtolower($code)]);
    }

    /** Absolute URL on the configured canonical host (not the request host). */
    public static function canonical(string $path): string
    {
        return Yii::$app->params['siteUrl'] . '/' . ltrim($path, '/');
    }

    public static function statusBadge(string $status): string
    {
        $s = strtolower($status);
        $cls = $s === 'active' ? 'ok' : (str_contains($s, 'dissolv') || str_contains($s, 'revok') ? 'bad' : 'warn');
        return '<span class="badge-pill-ei ' . $cls . '"><span class="dot"></span>' . Html::encode($status) . '</span>';
    }

    /** "123 Main St, Suite 4, Miami, FL 33101" -> "Miami, FL" (street is a Pro field). */
    public static function cityState(?string $addr): ?string
    {
        if (!$addr) {
            return null;
        }
        $parts = array_map('trim', explode(',', $addr));
        if (count($parts) < 2) {
            return null;
        }
        $last = explode(' ', $parts[count($parts) - 1])[0];
        return $parts[count($parts) - 2] . ', ' . $last;
    }

    /** Last N weekdays ending on $latest (YYYY-MM-DD). States rarely file on weekends. */
    public static function recentFilingDays(string $latest, int $n = 7): array
    {
        $out = [];
        $t = self::utc($latest);
        while (count($out) < $n) {
            if (!in_array((int) $t->format('N'), [6, 7], true)) {
                $out[] = $t->format('Y-m-d');
            }
            $t = $t->modify('-1 day');
        }
        return $out;
    }
}
