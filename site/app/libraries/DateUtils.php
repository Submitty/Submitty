<?php

namespace app\libraries;

use app\models\User;

/**
 * Class DateUtils
 *
 * Utility functions for interacting with dates and times
 */
class DateUtils {

    /** @var string Max limit we allow for parsed DateTimes to avoid compatibility issues between PHP and DB */
    const MAX_TIME = '9999-02-01 00:00:00';

    /**
     * Given two dates, give the interval of time in days between these two times. Any partial "days" are rounded
     * up to the nearest day in the positive direction. Thus if there's a difference of 2 days and 3 hours, then
     * the function would return 3 days. Likewise, if the difference was -3 hours, then 0 days would be returned.
     *
     * @param string|\DateTime $date1
     * @param string|\DateTime $date2
     *
     * @return int
     */
    public static function calculateDayDiff($date1, $date2 = "now"): int {
        if (!($date1 instanceof \DateTime)) {
            $date1 = new \DateTime($date1);
        }
        if (!($date2 instanceof \DateTime)) {
            $date2 = new \DateTime($date2);
        }
        // Set the period as "1 day" for the interval
        if ($date1 == $date2) {
            return 0;
        }
        $diff = $date1->diff($date2);
        $days_late = intval($diff->format('%r%a'));
        if ($date1 < $date2) {
            if ($diff->h > 0 || $diff->i > 0 || $diff->s > 0) {
                $days_late += 1;
            }
        }

        return $days_late;
    }

    /**
     * Validate a given timestamp by some format.
     *
     * Timestamp is validated such that when parsed into a TimeStamp on some format,
     * ensure that the day, month, and year do not change (e.g. using 01/32/2019 would
     * be parsed into DateTime 02/01/2019, so invalid timestamp).
     *
     * @see https://www.php.net/manual/en/datetime.createfromformat.php for valid formats
     */
    public static function validateTimestampForFormat(string $format, string $timestamp): bool {
        $datetime = \DateTime::createFromFormat($format, $timestamp);
        return $datetime !== false && $datetime->format($format) === $timestamp;
    }

    /**
     * Validates a given DateTime string.
     *
     * Ensures that the string is a valid combination of day, month,
     * and year and falls into one of the four formats:
     * mm-dd-yyyy
     * mm/dd/yyyy
     * mm-dd-yy
     * mm/dd/yy
     * For dates that are not valid, PHP will implicitly convert
     * the given string to the proper date and so we validate by
     * making sure the day/month/year do not change. For example,
     * giving '01-32-2019', PHP will return a DateTime for 02-01-2019.
     *
     * Passing in a totally invalid datestring will be parsed as "false".
     */
    public static function validateTimestamp(string $timestamp): bool {
        return static::validateTimestampForFormat('m-d-Y', $timestamp)
            || static::validateTimestampForFormat('m/d/Y', $timestamp)
            || static::validateTimestampForFormat('m-d-y', $timestamp)
            || static::validateTimestampForFormat('m/d/y', $timestamp);
    }

    /**
     * Parses a date string into a \DateTime object, or does nothing if $date is already a \DateTime object
     * Note: This will clamp the date to be earlier than MAX_TIME
     *
     * @param \DateTime|string $date The date to parse
     * @param \DateTimeZone $time_zone
     * @return \DateTime The parsed date
     * @throws \InvalidArgumentException If $date is not a string or a \DateTime, or not a valid \DateTime string
     */
    public static function parseDateTime($date, \DateTimeZone $time_zone): \DateTime {
        if (gettype($date) === 'string') {
            try {
                $date = new \DateTime($date, $time_zone);
            }
            catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid DateTime Format');
            }
        }
        elseif (!($date instanceof \DateTime)) {
            throw new \InvalidArgumentException('Passed object was not a DateTime object or a date string');
        }

        // Make sure we always set the timezone
        $date->setTimezone($time_zone);

        // Make sure we don't go above our range
        return min($date, new \DateTime(self::MAX_TIME, $time_zone));
    }

    /**
     * Converts a \DateTime object to a string in one place so if we change the format
     *  here, it changes everywhere
     *
     * @param \DateTime  $date The date to format
     * @param bool $add_utc_offset If the UTC offset should be part of the output
     * @return string The formatted date
     */
    public static function dateTimeToString(\DateTime $date, bool $add_utc_offset = true): string {
        return $date->format('Y-m-d H:i:s' . ($add_utc_offset ? 'O' : ''));
    }

    /**
     * Gets a json which contains the current server time broken up into specific fields
     * Formatting the data in this manner makes it easier to work with when instantiating javascript Date() objects
     *
     * @param $core Core core
     * @return array
     */
    public static function getServerTimeJson(Core $core): array {
        $time = new \DateTime('now', $core->getConfig()->getTimezone());

        return [
            'year' => $time->format('Y'),
            'month' => $time->format('m'),
            'day' => $time->format('j'),
            'hour' => $time->format('G'),
            'minute' => $time->format('i'),
            'second' => $time->format('s')
        ];
    }

    /**
     * Get the complete set of time zones a user may select.  This is essentially just the list of time zone
     * identifiers made available by PHP, however 'NOT_SET/NOT_SET' has been added to accommodate users that
     * have not yet set their time zone.  'UTC' has also been removed as users need not select this option.
     *
     * @return array All user selectable time zones
     */
    public static function getAvailableTimeZones(): array {
        $available_time_zones = array_merge(['NOT_SET/NOT_SET'], \DateTimeZone::listIdentifiers());

        // Get rid of 'UTC' time zone
        unset($available_time_zones[count($available_time_zones) - 1]);

        return $available_time_zones;
    }

    /**
     * Returns the list of available time zones in the increasing order of UTC offset
     * each list item(or time zone string) also contains info regarding the UTC offset
     * @return array
     */
    public static function getOrderedTZWithUTCOffset() {
        $available_time_zones = \DateTimeZone::listIdentifiers();

        // Get rid of 'UTC' time zone
        unset($available_time_zones[count($available_time_zones) - 1]);

        // sort the list with respect to UTC offset
        usort($available_time_zones, function ($tz1, $tz2) {
            $offset1 = intval(str_replace(":", "", DateUtils::getUTCOffset($tz1)));
            $offset2 = intval(str_replace(":", "", DateUtils::getUTCOffset($tz2)));
            return $offset1 === $offset2 ? 0 : ($offset2 > $offset1 ? 1 : -1);
        });
        // Attach the UTC offset value
        $timezone_with_offset = array_map(function ($tz) {
            return "(UTC" . DateUtils::getUTCOffset($tz) . ") " . $tz;
        }, $available_time_zones);
        // Add 'NOT_SET/NOT_SET' on top of the ordered list
        return array_merge(['NOT_SET/NOT_SET'], $timezone_with_offset);
    }

    /**
     * Compute the offset in hours:minutes between the given time zone identifier string, and the UTC timezone.
     *
     * @param string $time_zone A time zone identifier string collected from getAvailableTimeZones()
     * @return string The UTC offset, for example '+9:30' or '-4:00'
     */
    public static function getUTCOffset(string $time_zone): string {
        if ($time_zone === 'NOT_SET/NOT_SET') {
            return 'NOT SET';
        }

        $time_stamp = new \DateTime('now', new \DateTimeZone($time_zone));

        return $time_stamp->format('P');
    }

    /**
     * Converts the given time stamp string into the given user's time zone and then returns it according
     * to the given format.
     *
     * @param User $user
     * @param string $time_stamp
     * @param string $format
     * @return string
     */
    public static function convertTimeStamp(User $user, string $time_stamp, string $format): string {
        $time = self::parseDateTime($time_stamp, $user->getUsableTimeZone());
        return $time->format($format);
    }

    /**
     * Get the current time formatted so that it can be used as a filename.  Naming files in this way makes files
     * easily sorted by their creation time.
     *
     * @return string
     */
    public static function getFileNameTimeStamp(): string {
        $time = new \DateTime();
        return $time->format('YmdHis');
    }
}
