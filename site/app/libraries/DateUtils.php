<?php

namespace app\libraries;

use \DateTime;
use \DateInterval;

/**
 * Class DateUtils
 *
 * Utility functions for interacting with dates and times
 */
class DateUtils {
    
    /**
     * Given two dates, give the interval of time in days between these two times. Any partial "days" are rounded
     * up to the nearest day in the positive direction. Thus if there's a difference of 2 days and 3 hours, then
     * the function would return 3 days. Likewise, if the difference was -3 hours, then 0 days would be returned.
     *
     * @param string|DateTime $date1
     * @param string|DateTime $date2
     *
     * @return int
     */
    public static function calculateDayDiff($date1, $date2="Now") {
        if (!($date1 instanceof DateTime)) {
            $date1 = new DateTime($date1);
        }
        if (!($date2 instanceof DateTime)) {
            $date2 = new DateTime($date2);
        }
        // Set the period as "1 day" for the interval
        if ($date1 == $date2) {
            return 0;
        }
        else if ($date1 < $date2) {
            $date1->sub(new DateInterval("P1D"));
        }
        return intval($date1->diff($date2)->format('%r%a'));
    }
    
}