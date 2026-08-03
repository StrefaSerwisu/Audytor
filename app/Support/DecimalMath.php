<?php

namespace App\Support;

use RuntimeException;

class DecimalMath
{
    public function __construct()
    {
        if (! extension_loaded('bcmath')) {
            throw new RuntimeException('Silnik wyceny wymaga rozszerzenia PHP BCMath.');
        }
    }

    public function normalize(string|int $value): string
    {
        return $this->round((string) $value);
    }

    public function add(string|int $left, string|int $right): string
    {
        return $this->round(bcadd((string) $left, (string) $right, 6));
    }

    public function subtract(string|int $left, string|int $right): string
    {
        return $this->round(bcsub((string) $left, (string) $right, 6));
    }

    public function multiply(string|int $left, string|int $right): string
    {
        return $this->round(bcmul((string) $left, (string) $right, 6));
    }

    public function percent(string|int $value, string|int $percent): string
    {
        return $this->round(bcdiv(bcmul((string) $value, (string) $percent, 8), '100', 8));
    }

    public function max(string|int $left, string|int $right): string
    {
        return bccomp((string) $left, (string) $right, 6) >= 0 ? $this->normalize($left) : $this->normalize($right);
    }

    public function min(string|int $left, string|int $right): string
    {
        return bccomp((string) $left, (string) $right, 6) <= 0 ? $this->normalize($left) : $this->normalize($right);
    }

    private function round(string $value): string
    {
        $adjustment = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd($value, $adjustment, 2);
    }
}
