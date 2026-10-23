<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::query()->where('key', $key)->first();

        if (! $row) {
            return $default;
        }

        return json_decode($row->value, true);
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }
}
