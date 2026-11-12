<?php

namespace App\Services;

use App\Enums\OverdueLevel;
use Carbon\CarbonInterface;

class OverduePolicy
{
    public function daysLate(CarbonInterface $dueAt, ?CarbonInterface $at = null): int
    {
        $seconds = $dueAt->diffInSeconds($at ?? now(), false);

        $secondsPerDay = CarbonInterface::HOURS_PER_DAY * CarbonInterface::MINUTES_PER_HOUR * CarbonInterface::SECONDS_PER_MINUTE;

        return $seconds > 0 ? (int) ceil($seconds / $secondsPerDay) : 0;
    }

    public function level(int $daysLate): ?OverdueLevel
    {
        if ($daysLate <= 0) {
            return null;
        }

        $duration = config('library.loan_duration_days');

        return match (true) {
            $daysLate <= $duration / 3 => OverdueLevel::Light,
            $daysLate <= 2 * $duration / 3 => OverdueLevel::Medium,
            default => OverdueLevel::High,
        };
    }
}
