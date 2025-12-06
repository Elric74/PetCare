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
}
