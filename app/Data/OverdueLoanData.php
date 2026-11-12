<?php

namespace App\Data;

use App\Enums\OverdueLevel;
use Carbon\CarbonImmutable;

final readonly class OverdueLoanData
{
    public function __construct(
        public int $id,
        public int $copyId,
        public string $copyQrCode,
        public string $title,
        public string $author,
        public CarbonImmutable $borrowedAt,
        public CarbonImmutable $dueAt,
        public int $daysLate,
        public OverdueLevel $level,
    ) {}
}
