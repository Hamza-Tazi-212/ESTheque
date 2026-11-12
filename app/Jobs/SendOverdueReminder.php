<?php

namespace App\Jobs;

use App\Mail\OverdueReminder;
use App\Services\OverdueReport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOverdueReminder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 86400;

    public array $backoff = [60, 300];

    public function __construct(public readonly int $studentId) {}

    public function uniqueId(): string
    {
        return (string) $this->studentId;
    }

    public function handle(OverdueReport $reports): void
    {
        // Reload both identity and loans at execution, rather than serializing a stale email.
        $report = $reports->forStudent($this->studentId);
        if ($report !== null) {
            Mail::to($report->student->email)->send(new OverdueReminder($report));
        }
    }
}
