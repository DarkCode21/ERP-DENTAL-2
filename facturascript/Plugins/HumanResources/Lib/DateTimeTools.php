<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib;

/**
 * Complementary processes (Utilities) for the treatment of dates and times
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class DateTimeTools
{

    const DATE_FORMAT = 'Y-m-d';

    /**
     * Returns the date starting from the current date and modified
     * according to the value of the parameter.
     * Examples of modifiers:
     *   - +1 day
     *   - -1 day
     *   - -7 day
     *
     * @param string $modifier
     * @return string
     */
    public static function dateFromCurrent(string $modifier): string
    {
        $date = strtotime($modifier, strtotime(date(self::DATE_FORMAT)));
        return date(self::DATE_FORMAT, $date);
    }

    /**
     * Indicates if one date is greater than another.
     * If indicated, equals to greater than is considered.
     * If the maximum date is not reported, the current day is assumed.
     *
     * @param string $value
     * @param bool $orEqual
     * @param string $maxDate
     * @return bool
     */
    public static function dateGreaterThan(string $value, bool $orEqual = false, string $maxDate = ''): bool
    {
        if ($maxDate === '') {
            $maxDate = date(self::DATE_FORMAT);
        }

        return $orEqual
            ? (strtotime($value) >= strtotime($maxDate))
            : (strtotime($value) > strtotime($maxDate));
    }

    /**
     * Indicates if one date is less than another.
     * If the maximum date is not reported, the current day is assumed.
     *
     * @param string $value
     * @param string $maxDate
     * @return bool
     */
    public static function dateLessThan(string $value, string $maxDate = ''): bool
    {
        if ($maxDate === '') {
            $maxDate = date(self::DATE_FORMAT);
        }

        return (strtotime($value) < strtotime($maxDate));
    }

    /**
     * Return the day of the week for the indicated date into the ISO 8601:2004 calendar.
     *
     * @param string $date
     * @return int
     */
    public static function dayOfWeek(string $date = ''): int
    {
        if (empty($date)) {
            $date = date('Y-m-d');
        }
        $dow = date('w', strtotime($date));
        return $dow > 0 ? $dow : 7;
    }

    /**
     * Calculate number days between two dates
     *
     * @param string $start
     * @param string $end
     * @param bool $increment
     * @return integer
     */
    public static function daysBetween(string $start, string $end, bool $increment = false): int
    {
        if (empty($start) || empty($end)) {
            return 0;
        }

        $diff = strtotime($end) - strtotime($start);
        $result = ceil($diff / 86400);
        if ($increment) {
            ++$result;
        }
        return $result;
    }

    /**
     * Convert a time in decimal format to time in days/hours/minute format.
     *
     * @param float $time
     * @return array
     */
    public static function decimalTimeToString(float $time): array
    {
        $values = explode('.', $time);
        $minutes = count($values) > 1
            ? (float) ('0.' . $values[1])
            : 0;

        return [
            'days' => intdiv($values[0], 24),
            'hours' => $values[0] % 24,
            'minutes' => (int) ($minutes * 60),
        ];
    }

    /**
     * Check if the date is greater than the current date.
     *
     * @param string $date
     * @param string $time
     * @return bool
     */
    public static function greaterCurrentDateTime(string $date, string $time): bool
    {
        if (empty($date)) {
            $date = date('d-m-Y');
        }

        if (empty($time)) {
            $time = date('H:i:s');
        }
        $current = strtotime(date('d-m-Y H:i:s',time()));
        $datetime = strtotime($date . ' ' . $time);
        return $datetime > $current;
    }

    /**
     * Calculate the last day for one month.
     *
     * @param int $year
     * @param int $month
     * @return int
     */
    public static function lastDayMonth(int $year, int $month): int
    {
        return date('d', mktime(0, 0, 0, $month+1, 1, $year)-1);
    }

    /**
     * Calculate date the last day of the month.
     *
     * @param int $year
     * @param int $month
     * @return string
     */
    public static function lastDateMonth(int $year = 0, int $month = 0): string
    {
        if ($year === 0) {
            $year = date('Y');
        }
        if ($month === 0) {
            $month = date('m');
        }
        return date(self::DATE_FORMAT, mktime(0, 0, 0, $month+1, 1, $year)-1);
    }

    /**
     * Get monday date of date.
     *
     * @param string $date
     * @param string $format
     * @return string
     */
    public static function mondayFromDate(string $date, string $format = 'd-m-Y'): string
    {
        $day = self::dayOfWeek($date);
        return $day === 1
            ? $date
            : date($format, strtotime('-'. ($day - 1) .' days', strtotime($date)));
    }

    /**
     * Calculate the number of hours between two times
     *
     * @param string $start
     * @param string $end
     * @return float
     */
    public static function timeDifferenceInHours(string $start, string $end): float
    {
        if (empty($start) || empty($end)) {
            return 0;
        }

        $startHour = date_parse_from_format('H:i:s', $start);
        $endHour = date_parse_from_format('H:i:s', $end);

        $ini = ($startHour['hour'] * 3600) + ($startHour['minute'] * 60) + $startHour['second'];
        $fin = ($endHour['hour'] * 3600) + ($endHour['minute'] * 60) + $endHour['second'];

        $dif = ($fin - $ini) / 3600;
        return round($dif, 4);
    }
}
