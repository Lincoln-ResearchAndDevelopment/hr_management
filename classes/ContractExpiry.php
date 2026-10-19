<?php

/**
 * Works out where a contract stands against today's date, in one place, so the HR dashboard,
 * the reminder emails and the daily reminder script always say the same thing.
 *
 * A contract is valid up to and including its end date: on that day it "ends today"; only from
 * the next day is it "expired".
 */
class ContractExpiry
{
    /** The app's time zone (Nigeria) - the same one config.php sets for PHP and MySQL. */
    private const TIMEZONE = 'Africa/Lagos';

    /** Today as a date at midnight in the app's time zone. */
    public static function today()
    {
        return new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE));
    }

    /**
     * Whole days from today to the end date: positive = still to come, 0 = ends today,
     * negative = already ended that many days ago.
     */
    public static function daysUntil($endDate)
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        $end = new DateTimeImmutable(substr((string) $endDate, 0, 10) . ' 00:00:00', $tz);
        return (int) self::today()->diff($end)->format('%r%a');
    }

    /**
     * How to say it. Returns:
     *   days    - the number from daysUntil()
     *   state   - 'expired' | 'today' | 'soon'
     *   text    - e.g. "expires today", "expires in 5 days", "expired 273 days ago"
     *   dateText- the end date in full, e.g. "Wednesday, October 14, 2026"
     */
    public static function describe($endDate)
    {
        $days = self::daysUntil($endDate);
        $dateText = date('l, F j, Y', strtotime($endDate));

        if ($days < 0) {
            $n = abs($days);
            return ['days' => $days, 'state' => 'expired', 'text' => 'expired ' . $n . ' day' . ($n === 1 ? '' : 's') . ' ago', 'dateText' => $dateText];
        }
        if ($days === 0) {
            return ['days' => 0, 'state' => 'today', 'text' => 'expires today', 'dateText' => $dateText];
        }
        return ['days' => $days, 'state' => 'soon', 'text' => 'expires in ' . $days . ' day' . ($days === 1 ? '' : 's'), 'dateText' => $dateText];
    }
}
