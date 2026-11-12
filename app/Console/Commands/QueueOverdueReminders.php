<?php

namespace App\Console\Commands;

use App\Contracts\UserDirectory;
use App\Enums\UserRole;
use App\Jobs\SendOverdueReminder;
use App\Models\Loan;
use App\Support\AcademicEmail;
use Illuminate\Console\Command;

class QueueOverdueReminders extends Command
{
    protected $signature = 'library:send-overdue-reminders';

    protected $description = 'Mettre en file un email par étudiant ayant des exemplaires en retard';

    public function handle(UserDirectory $directory): int
    {
        $count = 0;
        Loan::overdue()->select('student_id')->distinct()
            ->chunkById(200, function ($loans) use ($directory, &$count) {
                $users = $directory->findByIds($loans->pluck('student_id')->all());
                foreach ($users as $user) {
                    if ($user->role === UserRole::Student && AcademicEmail::isValid($user->email)) {
                        SendOverdueReminder::dispatch($user->id);
                        $count++;
                    }
                }
            }, 'student_id');

        $this->info($count.' étudiant(s) concerné(s) ; envois confiés à la file d’attente.');

        return self::SUCCESS;
    }
}
