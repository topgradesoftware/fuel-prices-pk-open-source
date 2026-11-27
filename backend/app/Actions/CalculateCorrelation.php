<?php

namespace App\Actions;

class CalculateCorrelation
{
    /**
     * Calculate Pearson Correlation Coefficient between two datasets.
     * Both arrays must be of equal length.
     */
    public function execute(array $x, array $y): array
    {
        if (count($x) !== count($y) || count($x) < 2) {
            return $this->formatResult(0, 'Invalid dataset sizes');
        }

        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        
        $sumX2 = 0;
        $sumY2 = 0;
        $sumXY = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX2 += pow($x[$i], 2);
            $sumY2 += pow($y[$i], 2);
            $sumXY += ($x[$i] * $y[$i]);
        }

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - pow($sumX, 2)) * (($n * $sumY2) - pow($sumY, 2)));

        if ($denominator == 0) {
            return $this->formatResult(0, 'No variance in data');
        }

        $r = $numerator / $denominator;
        
        return $this->formatResult($r);
    }

    private function formatResult(float $r, ?string $error = null): array
    {
        $strength = 'No Correlation';
        $absR = abs($r);

        if ($absR >= 0.7) {
            $strength = 'Strong ' . ($r > 0 ? 'Positive' : 'Negative');
        } elseif ($absR >= 0.3) {
            $strength = 'Weak ' . ($r > 0 ? 'Positive' : 'Negative');
        }

        return [
            'coefficient' => round($r, 4),
            'strength' => $strength,
            'error' => $error,
            'disclaimer' => 'CORRELATION ≠ CAUSATION. A high correlation score indicates that two metrics move together mathematically, but does not imply that one causes the other.'
        ];
    }
}
