<?php

namespace App\Services\Analytics;

class PearsonCorrelationService
{
    /**
     * Compute Pearson correlation coefficient (r) between two numeric arrays.
     *
     * @param  list<float|int>  $x
     * @param  list<float|int>  $y
     */
    public function compute(array $x, array $y): float
    {
        $n = count($x);
        if ($n < 2 || $n !== count($y)) {
            return 0.0;
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $numerator = 0.0;
        $denomX = 0.0;
        $denomY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $diffX = (float) $x[$i] - $meanX;
            $diffY = (float) $y[$yKey = $i] - $meanY;

            $numerator += $diffX * $diffY;
            $denomX += $diffX * $diffX;
            $denomY += $diffY * $diffY;
        }

        if ($denomX <= 0.0 || $denomY <= 0.0) {
            return 0.0;
        }

        $r = $numerator / sqrt($denomX * $denomY);

        // Clamp to [-1.0, 1.0] to prevent floating point drift
        return round(max(-1.0, min(1.0, $r)), 4);
    }

    /**
     * Compute linear regression parameters (y = mx + c) and r / r^2.
     *
     * @param  list<float|int>  $x
     * @param  list<float|int>  $y
     * @return array{
     *     slope: float,
     *     intercept: float,
     *     r: float,
     *     r_squared: float
     * }
     */
    public function linearRegression(array $x, array $y): array
    {
        $n = count($x);
        if ($n < 2 || $n !== count($y)) {
            $meanY = $n > 0 ? round(array_sum($y) / $n, 2) : 0.0;

            return [
                'slope' => 0.0,
                'intercept' => $meanY,
                'r' => 0.0,
                'r_squared' => 0.0,
            ];
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $numerator = 0.0;
        $denomX = 0.0;
        $denomY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $diffX = (float) $x[$i] - $meanX;
            $diffY = (float) $y[$i] - $meanY;

            $numerator += $diffX * $diffY;
            $denomX += $diffX * $diffX;
            $denomY += $diffY * $diffY;
        }

        if ($denomX <= 0.0) {
            return [
                'slope' => 0.0,
                'intercept' => round($meanY, 2),
                'r' => 0.0,
                'r_squared' => 0.0,
            ];
        }

        $slope = $numerator / $denomX;
        $intercept = $meanY - ($slope * $meanX);

        $r = ($denomY > 0.0)
            ? max(-1.0, min(1.0, $numerator / sqrt($denomX * $denomY)))
            : 0.0;

        return [
            'slope' => round($slope, 4),
            'intercept' => round($intercept, 2),
            'r' => round($r, 4),
            'r_squared' => round($r * $r, 4),
        ];
    }

    /**
     * Generate 2 endpoint coordinates for plotting the regression line across min and max X.
     *
     * @param  list<float|int>  $x
     * @param  list<float|int>  $y
     * @return list<array{x: float, y: float}>
     */
    public function computeTrendLine(array $x, array $y): array
    {
        if (count($x) < 2 || count($x) !== count($y)) {
            return [];
        }

        $minX = (float) min($x);
        $maxX = (float) max($x);

        if ($minX === $maxX) {
            return [];
        }

        $reg = $this->linearRegression($x, $y);
        $minY = round(($reg['slope'] * $minX) + $reg['intercept'], 2);
        $maxY = round(($reg['slope'] * $maxX) + $reg['intercept'], 2);

        return [
            ['x' => round($minX, 1), 'y' => max(0.0, $minY)],
            ['x' => round($maxX, 1), 'y' => max(0.0, $maxY)],
        ];
    }
}
