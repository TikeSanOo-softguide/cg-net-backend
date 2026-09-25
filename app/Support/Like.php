<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Case-insensitive LIKE that works on MySQL (like) and PostgreSQL (ilike). */
final class Like
{
    public static function operator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
