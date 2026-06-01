<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class IsoDate
{
    public static function format(DateTimeInterface|CarbonInterface|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $carbon = $value instanceof CarbonInterface ? $value : Carbon::parse($value);

        return $carbon->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function parse(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->utc();
    }
}
