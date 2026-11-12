<?php

namespace Tests\Feature;

use App\Enums\OverdueLevel;
use App\Services\OverduePolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OverduePolicyTest extends TestCase
{
    #[DataProvider('latenessBoundaries')]
    public function test_days_and_levels_at_the_deadline_and_third_boundaries(int $seconds, int $days, ?OverdueLevel $level): void
    {
        $policy = app(OverduePolicy::class);
        $due = CarbonImmutable::parse('2026-10-16 12:00:00', 'UTC');
        $actual = $policy->daysLate($due, $due->addSeconds($seconds));

        $this->assertSame($days, $actual);
        $this->assertSame($level, $policy->level($actual));
    }

    public static function latenessBoundaries(): array
    {
        return [
            'before deadline' => [-1, 0, null],
            'at deadline' => [0, 0, null],
            'first second late' => [1, 1, OverdueLevel::Light],
            'one day' => [86400, 1, OverdueLevel::Light],
            'ten days' => [10 * 86400, 10, OverdueLevel::Light],
            'eleventh day started' => [10 * 86400 + 1, 11, OverdueLevel::Medium],
            'twenty days' => [20 * 86400, 20, OverdueLevel::Medium],
            'twenty-first day started' => [20 * 86400 + 1, 21, OverdueLevel::High],
        ];
    }

    public function test_thresholds_follow_the_single_duration_parameter(): void
    {
        config(['library.loan_duration_days' => 60]);
        $policy = app(OverduePolicy::class);

        $this->assertSame(OverdueLevel::Light, $policy->level(20));
        $this->assertSame(OverdueLevel::Medium, $policy->level(21));
        $this->assertSame(OverdueLevel::Medium, $policy->level(40));
        $this->assertSame(OverdueLevel::High, $policy->level(41));
    }
}
