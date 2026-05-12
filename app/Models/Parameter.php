<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get($key, $default = null)
    {
        $param = self::where('key', $key)->first();
        return $param ? $param->value : $default;
    }

    public static function set($key, $value)
    {
        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Whether a stored flag is "on" (for dashboard toggles, etc.).
     * Missing key uses $default. Values 0/false/no/off/disabled (case-insensitive) are off.
     */
    public static function isEnabled(string $key, bool $default = true): bool
    {
        $param = self::where('key', $key)->first();
        if ($param === null || $param->value === null || $param->value === '') {
            return $default;
        }

        $v = strtolower(trim((string) $param->value));
        if (in_array($v, ['0', 'false', 'no', 'off', 'disabled'], true)) {
            return false;
        }

        if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
            return true;
        }

        return $default;
    }
}
