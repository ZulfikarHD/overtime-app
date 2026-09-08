<?php

if (! function_exists('bcadd')) {
    function bcadd(string|int|float $num1, string|int|float $num2, ?int $scale = null): string
    {
        $scale ??= 0;
        $result = (float) $num1 + (float) $num2;

        return number_format($result, $scale, '.', '');
    }
}

if (! function_exists('bcmul')) {
    function bcmul(string|int|float $num1, string|int|float $num2, ?int $scale = null): string
    {
        $scale ??= 0;
        $result = (float) $num1 * (float) $num2;

        return number_format($result, $scale, '.', '');
    }
}

if (! function_exists('bccomp')) {
    function bccomp(string|int|float $num1, string|int|float $num2, ?int $scale = null): int
    {
        $scale ??= 0;
        $val1 = round((float) $num1, $scale);
        $val2 = round((float) $num2, $scale);
        $diff = $val1 - $val2;

        if (abs($diff) < 1e-9) {
            return 0;
        }

        return $diff > 0 ? 1 : -1;
    }
}

if (! function_exists('bcsub')) {
    function bcsub(string|int|float $num1, string|int|float $num2, ?int $scale = null): string
    {
        $scale ??= 0;
        $result = (float) $num1 - (float) $num2;

        return number_format($result, $scale, '.', '');
    }
}

if (! function_exists('bcdiv')) {
    function bcdiv(string|int|float $num1, string|int|float $num2, ?int $scale = null): ?string
    {
        $scale ??= 0;
        $divisor = (float) $num2;
        if (abs($divisor) < 1e-9) {
            return null;
        }
        $result = (float) $num1 / $divisor;

        return number_format($result, $scale, '.', '');
    }
}
