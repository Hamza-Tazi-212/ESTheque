<?php

namespace App\Data;

use App\Enums\OverdueLevel;

final readonly class OverdueStudentData
{
    /** @param list<OverdueLoanData> $loans */
    public function __construct(
        public DirectoryUser $student,
        public array $loans,
        public int $maxDaysLate,
        public OverdueLevel $level,
    ) {}
}
