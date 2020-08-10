<?php

namespace app\libraries;

/**
 * Class NumberUtils
 *
 * Utility functions for interacting with numbers
 */
class NumberUtils {

    /**
     * Gives the closest number to the `value` with respect to `precision`
     * @param float $value The number to round
     * @param float $precision The precision with calculation to be made
     * @return float The rounded result to the nearest multiple of precision
     */
    public static function roundPointValue(float $value, float $precision): float {

        // No $precision, no rounding
        if ($precision === 0.0) {
            return $value;
        }

        $value = floatval($value);
        $qtnt_f = round($value / $precision, 2);
        $qtnt_i = (int) ($value / $precision);

        if ($qtnt_i == $qtnt_f) {
            return $value;
        }

        $mod = $value - $qtnt_i * $precision;

        $shift = null;
        // difference is to little to be considered , No shifting needed
        if (abs($mod) < $precision / 2) {
            $shift = 0;
        }
        else {
            // shift to one unit in the direction from zero to the quotient
            $shift = $mod > 0 ? 1 : -1;
        }

        return round(($qtnt_i + $shift) * $precision, 3);
    }

    /**
     * @param int $array_length
     * @param string $seed
     * @return array the randomized indices array
     */
    public static function getRandomIndices(int $array_length, string $seed): array {
        // creating an array which is holding the indices to be shuffled.
        $randomizedIndices = [];
        for ($i = 0; $i < $array_length; $i++) {
            $randomizedIndices[] = $i;
        }

        $hash = str_split(hash('sha256', $seed));
        // generating a seed value for the random function
        $seedValue = 0;
        foreach ($hash as $hashChar) {
            $seedValue += ord($hashChar);
        }
        // setting the seed value for getting pseudo random no.
        srand($seedValue);
        // inspired from fisher-yates algorithm
        for ($i = $array_length - 1; $i > 0; $i--) {
            // Pick a random index from 0 to i
            $j = rand() % ($i + 1);
            $tmp = $randomizedIndices[$i];
            $randomizedIndices[$i] = $randomizedIndices[$j];
            $randomizedIndices[$j] = $tmp;
        }

        return $randomizedIndices;
    }
}
