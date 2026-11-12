<?php

namespace Tests\Feature;

use App\Jobs\SendOverdueReminder;
use App\Mail\OverdueReminder;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\User;
use App\Services\OverdueReport;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OverdueRemindersTest extends TestCase
{
    use RefreshDatabase;

    private int $hamzaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->seed(DemoDataSeeder::class);
        $this->hamzaId = User::where('email', 'hamza.tazi5@usmba.ac.ma')->value('id');
        Mail::fake();
    }

    public function test_command_queues_one_job_per_late_student_without_sending_mail_inline(): void
    {
        Queue::fake();

        $this->artisan('library:send-overdue-reminders')->assertSuccessful();

        Queue::assertPushed(SendOverdueReminder::class, 2);
        Queue::assertPushed(SendOverdueReminder::class, fn ($job) => $job->studentId === $this->hamzaId);
        Mail::assertNothingSent();
    }

    public function test_repeated_dispatch_does_not_duplicate_pending_student_jobs(): void
    {
        Queue::fake();

        $this->artisan('library:send-overdue-reminders')->assertSuccessful();
        $this->artisan('library:send-overdue-reminders')->assertSuccessful();

        Queue::assertPushed(SendOverdueReminder::class, 2);
    }

    public function test_ineligible_addresses_or_roles_do_not_receive_jobs(): void
    {
        Queue::fake();
        User::findOrFail($this->hamzaId)->update(['role' => 'manager']);
        User::where('email', 'salma.elalami2@usmba.ac.ma')->update(['email' => 'external@gmail.com']);

        $this->artisan('library:send-overdue-reminders')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_no_jobs_are_queued_when_no_active_loans_are_late(): void
    {
        Queue::fake();
        Loan::overdue()->update(['returned_at' => now()]);

        $this->artisan('library:send-overdue-reminders')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_worker_groups_all_current_late_copies_in_one_email(): void
    {
        (new SendOverdueReminder($this->hamzaId))->handle(app(OverdueReport::class));

        Mail::assertSent(OverdueReminder::class, function (OverdueReminder $mail) {
            $this->assertCount(2, $mail->report->loans);
            $html = $mail->render();
            $this->assertStringContainsString('copy:DEMO-ALG-01', $html);
            $this->assertStringContainsString('copy:DEMO-BDD-01', $html);
            $this->assertStringNotContainsString('copy:DEMO-WEB-01', $html);
            $this->assertStringContainsString('Retard léger', $html);
            $this->assertStringContainsString('Retard moyen', $html);

            return $mail->hasTo('hamza.tazi5@usmba.ac.ma');
        });
        Mail::assertSentCount(1);
    }

    public function test_returned_or_cancelled_copies_are_rechecked_before_delivery(): void
    {
        $job = new SendOverdueReminder($this->hamzaId);
        $loans = Loan::overdue()->where('student_id', $this->hamzaId)->get();
        $loans[0]->update(['returned_at' => now()]);
        $loans[1]->update(['cancelled_at' => now()]);

        $job->handle(app(OverdueReport::class));

        Mail::assertNothingSent();
    }

    public function test_partial_return_removes_only_that_copy_from_the_email(): void
    {
        $loan = Loan::overdue()->where('student_id', $this->hamzaId)->with('copy')->firstOrFail();
        $excludedQr = $loan->copy->qr_code;
        $loan->update(['returned_at' => now()]);

        (new SendOverdueReminder($this->hamzaId))->handle(app(OverdueReport::class));

        Mail::assertSent(OverdueReminder::class, function ($mail) use ($excludedQr) {
            return count($mail->report->loans) === 1
                && ! str_contains($mail->render(), $excludedQr);
        });
    }

    public function test_job_reloads_email_and_role_from_the_directory_at_execution(): void
    {
        $job = new SendOverdueReminder($this->hamzaId);
        $user = User::findOrFail($this->hamzaId);
        $user->update(['email' => 'nouvelle.adresse1@usmba.ac.ma']);

        $job->handle(app(OverdueReport::class));

        Mail::assertSent(OverdueReminder::class, fn ($mail) => $mail->hasTo('nouvelle.adresse1@usmba.ac.ma'));
        Mail::fake();
        $user->update(['role' => 'manager']);
        $job->handle(app(OverdueReport::class));
        Mail::assertNothingSent();
    }

    public function test_job_ignores_unknown_students_and_external_emails(): void
    {
        User::findOrFail($this->hamzaId)->update(['email' => 'external@gmail.com']);

        (new SendOverdueReminder($this->hamzaId))->handle(app(OverdueReport::class));
        (new SendOverdueReminder(999))->handle(app(OverdueReport::class));

        Mail::assertNothingSent();
    }

    public function test_database_queue_can_serialize_and_process_a_student_job(): void
    {
        config(['queue.default' => 'database']);
        SendOverdueReminder::dispatch($this->hamzaId);
        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        Mail::assertSent(OverdueReminder::class, 1);
    }

    public function test_large_batch_crosses_chunk_boundaries_without_skipping_students(): void
    {
        Queue::fake();
        $bookId = Copy::firstOrFail()->book_id;
        foreach (User::factory()->count(201)->create() as $student) {
            $copy = Copy::create(['book_id' => $bookId, 'qr_code' => 'copy:bulk:'.$student->id, 'status' => 'borrowed']);
            Loan::create([
                'student_id' => $student->id, 'copy_id' => $copy->id,
                'borrowed_at' => now()->subDays(config('library.loan_duration_days') + 1),
                'due_at' => now()->subDay(),
            ]);
        }

        $this->artisan('library:send-overdue-reminders')->assertSuccessful();

        Queue::assertPushed(SendOverdueReminder::class, 203);
    }

    public function test_schedule_runs_daily_at_eight_in_the_moroccan_timezone(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'library:send-overdue-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Africa/Casablanca', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->travelTo(CarbonImmutable::parse('2026-10-16 08:00:00', 'Africa/Casablanca')->utc());
        $this->assertTrue($event->isDue($this->app));
        $this->travel(1)->minutes();
        $this->assertFalse($event->isDue($this->app));
    }
}
