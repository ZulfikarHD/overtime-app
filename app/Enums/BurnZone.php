<?php

namespace App\Enums;

/**
 * 3-zone tolerance rule applied to every burn / index indicator.
 *
 * Hijau  : index <= green_max_pct
 * Kuning : green_max_pct < index <= yellow_max_pct
 * Merah  : index > yellow_max_pct
 */
enum BurnZone: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    /**
     * Classify an index percentage against the configured zone boundaries.
     */
    public static function classify(float $pct, float $greenMaxPct, float $yellowMaxPct): self
    {
        return match (true) {
            $pct <= $greenMaxPct => self::Green,
            $pct <= $yellowMaxPct => self::Yellow,
            default => self::Red,
        };
    }

    /**
     * Indonesian display label for the zone.
     */
    public function label(): string
    {
        return match ($this) {
            self::Green => 'Hijau',
            self::Yellow => 'Kuning',
            self::Red => 'Merah',
        };
    }

    /**
     * Semantic status label for the zone.
     */
    public function statusLabel(): string
    {
        return match ($this) {
            self::Green => 'Aman',
            self::Yellow => 'Waspada',
            self::Red => 'Kritis',
        };
    }

    /**
     * Hex colour shared by charts and exports.
     */
    public function color(): string
    {
        return match ($this) {
            self::Green => '#16a34a',
            self::Yellow => '#f59e0b',
            self::Red => '#dc2626',
        };
    }

    /**
     * Human-readable range description for a given pair of boundaries.
     */
    public function rangeLabel(float $greenMaxPct, float $yellowMaxPct): string
    {
        $green = rtrim(rtrim(number_format($greenMaxPct, 2, '.', ''), '0'), '.');
        $yellow = rtrim(rtrim(number_format($yellowMaxPct, 2, '.', ''), '0'), '.');

        return match ($this) {
            self::Green => "Hijau (≤{$green}%)",
            self::Yellow => "Kuning ({$green}–{$yellow}%)",
            self::Red => "Merah (>{$yellow}%)",
        };
    }
}
