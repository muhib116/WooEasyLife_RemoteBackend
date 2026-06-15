<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CourierHubToken extends Model
{
    protected $fillable = [
        'partner',
        'token',
    ];

    public static function tokenForPartner(string $partner): string
    {
        $partner = strtolower(trim($partner));

        $row = static::query()->where('partner', $partner)->first();
        if ($row && $row->token) {
            return $row->token;
        }

        $token = Str::random(40);

        static::query()->updateOrCreate(
            ['partner' => $partner],
            ['token' => $token]
        );

        return $token;
    }
}
