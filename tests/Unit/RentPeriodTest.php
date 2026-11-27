<?php

namespace Tests\Unit;

use App\Core\RentPeriod;
use Tests\TestCase;

final class RentPeriodTest extends TestCase
{
    public function testPeriodRunsFromTheDueDayToTheDayBeforeTheNextOne(): void
    {
        self::assertSame('2026-01-01', RentPeriod::start(2026, 1, 1));
        self::assertSame('2026-01-31', RentPeriod::end('2026-01-01', 1));

        self::assertSame('2026-01-02', RentPeriod::start(2026, 1, 2));
        self::assertSame('2026-02-01', RentPeriod::end('2026-01-02', 2));

        self::assertSame('2026-01-03', RentPeriod::start(2026, 1, 3));
        self::assertSame('2026-02-02', RentPeriod::end('2026-01-03', 3));
    }

    public function testPeriodCrossesYearBoundaries(): void
    {
        self::assertSame('2025-12-15', RentPeriod::start(2025, 12, 15));
        self::assertSame('2026-01-14', RentPeriod::end('2025-12-15', 15));
    }

    public function testDateBeforeTheDueDayBelongsToThePreviousPeriod(): void
    {
        self::assertSame('2025-12-02', RentPeriod::forDate('2026-01-01', 2));
        self::assertSame('2026-01-02', RentPeriod::forDate('2026-01-02', 2));
        self::assertSame('2026-01-01', RentPeriod::forDate('2026-01-31', 1));
    }

    public function testMissingDueDayFallsBackToTheFirst(): void
    {
        self::assertSame(1, RentPeriod::dueDay(null));
        self::assertSame(1, RentPeriod::dueDay(99));
        self::assertSame('2026-02-28', RentPeriod::end('2026-02-01', null));
    }

    public function testOffsetAndStartsRespectTheDueDay(): void
    {
        self::assertSame('2025-12-02', RentPeriod::offset('2026-01-02', -1, 2));
        self::assertSame('2026-02-02', RentPeriod::offset('2026-01-02', 1, 2));
        self::assertSame(
            ['2025-12-02', '2026-01-02', '2026-02-02'],
            RentPeriod::starts('2025-12-15', '2026-02-10', 2)
        );
        self::assertSame('2026-01-02 – 2026-02-01', RentPeriod::label('2026-01-02', 2));
    }
}
