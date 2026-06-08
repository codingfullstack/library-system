<?php

namespace App\Support;

class GeneratesSlugs
{
    public static function from(string $value, string $fallback): string
    {
        $slug = mb_strtolower(trim($value));
        $slug = preg_replace('/[^\pL\pN]+/u', '-', $slug) ?: '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }
}
