<?php

namespace App\Core;

/**
 * Pure date math for rent payment periods. A period opens on the property's
 * due day and closes on the day before the next due day. Due days are limited
 * to 1-28; legacy properties without a day use the first of the month.
 */
class RentPeriod
{
    public const DEFAULT_DUE_DAY = 1;
    public const MAX_DUE_DAY = 28;

    public static function dueDay(mixed $value): int
    {
        $day = (int) $value;
        return $day >= 1 && $day <= self::MAX_DUE_DAY ? $day : self::DEFAULT_DUE_DAY;
    }

    public static function start(int $year, int $month, mixed $dueDay): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, self::dueDay($dueDay));
    }

    public static function end(string $start, mixed $dueDay): string
    {
        $date = new \DateTimeImmutable($start);
        $day = self::dueDay($dueDay);
        if ($day === 1) {
            return $date->format('Y-m-t');
        }
        $next = $date->modify('first day of next month');
        return $next->setDate((int) $next->format('Y'), (int) $next->format('m'), $day - 1)->format('Y-m-d');
    }

    public static function label(string $start, mixed $dueDay): string
    {
        return $start . ' – ' . self::end($start, $dueDay);
    }

    /** The period whose due day most recently fell on or before this date. */
    public static function forDate(string $date, mixed $dueDay): string
    {
        $value = new \DateTimeImmutable($date);
        $day = self::dueDay($dueDay);
        if ((int) $value->format('d') < $day) {
            $value = $value->modify('first day of previous month');
        }
        return self::start((int) $value->format('Y'), (int) $value->format('m'), $day);
    }

    public static function offset(string $start, int $months, mixed $dueDay): string
    {
        $value = (new \DateTimeImmutable(substr($start, 0, 7) . '-01'))
            ->modify(($months >= 0 ? '+' : '') . $months . ' months');
        return self::start((int) $value->format('Y'), (int) $value->format('m'), $dueDay);
    }

    /** @return list<string> */
    public static function starts(string $from, string $to, mixed $dueDay): array
    {
        $cursor = new \DateTimeImmutable(substr($from, 0, 7) . '-01');
        $last = new \DateTimeImmutable(substr($to, 0, 7) . '-01');
        $starts = [];
        while ($cursor <= $last) {
            $starts[] = self::start((int) $cursor->format('Y'), (int) $cursor->format('m'), $dueDay);
            $cursor = $cursor->modify('first day of next month');
        }
        return $starts;
    }
}
