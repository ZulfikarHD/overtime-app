<?php

use App\Enums\BurnZone;

test('classifies index percentages using inclusive upper boundaries', function (float $pct, BurnZone $expected) {
    expect(BurnZone::classify($pct, 70.0, 90.0))->toBe($expected);
})->with([
    'zero is green' => [0.0, BurnZone::Green],
    'plan 1200: index 840 (70%) is green' => [70.0, BurnZone::Green],
    'just above green limit is yellow' => [70.01, BurnZone::Yellow],
    'plan 1200: index 1080 (90%) is yellow' => [90.0, BurnZone::Yellow],
    'just above yellow limit is red' => [90.01, BurnZone::Red],
    'overrun is red' => [135.5, BurnZone::Red],
]);

test('describes the range label with the configured boundaries', function () {
    expect(BurnZone::Green->rangeLabel(70.0, 90.0))->toBe('Hijau (≤70%)')
        ->and(BurnZone::Yellow->rangeLabel(70.0, 90.0))->toBe('Kuning (70–90%)')
        ->and(BurnZone::Red->rangeLabel(72.5, 90.0))->toBe('Merah (>90%)')
        ->and(BurnZone::Yellow->rangeLabel(72.5, 90.0))->toBe('Kuning (72.5–90%)');
});

test('exposes a distinct colour per zone', function () {
    expect(collect(BurnZone::cases())->map->color()->unique())->toHaveCount(3);
});
