<?php

namespace Tests\Feature;

use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Enums\UserRole;
use App\Models\Loan;
use App\Models\User;
use App\Services\OverdueReport;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $hamzaId;

    private int $managerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->startOfSecond());
        $this->seed(DemoDataSeeder::class);
        $this->hamzaId = User::where('email', 'hamza.tazi5@usmba.ac.ma')->value('id');
        $this->managerId = User::where('role', 'manager')->value('id');
    }

    public function test_dashboard_counts_students_once_and_classifies_copies_individually(): void
    {
        $this->loginManager();
        Queue::fake();
        Mail::fake();

        $response = $this->get(route('manager.index'))->assertOk()
            ->assertSee('Tableau de bord')->assertSee('08:00')
            ->assertSee('Hamza Tazi')->assertSee('Salma El Alami')->assertSee('mailto:?bcc=', false);

        $this->assertSame(2, $response->viewData('lateStudentCount'));
        $this->assertSame(3, $response->viewData('overdue'));
        $this->assertSame(21, $response->viewData('longest'));
        $this->assertCount(3, $response->viewData('lateLoans'));
        $this->assertCount(2, $response->viewData('reminderEmails'));
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }


    public function test_dashboard_counts_active_loans_by_moroccan_day_and_filters_returns(): void
    {
        $now = \Illuminate\Support\Carbon::parse('2026-11-10 22:30:00', 'UTC');
        $this->travelTo($now);
        $loans = Loan::orderBy('id')->get();
        $loans[0]->update(['due_at' => $now->copy()->addMinutes(10)]);
        $loans[1]->update(['due_at' => $now->copy()->addMinutes(40)]);
        $loans[2]->update(['due_at' => $now->copy()->addDays(2)]);
        $loans[3]->update(['due_at' => $now->copy()->subMinute()]);
        $loans[4]->update(['returned_at' => $now->copy()->subDay(), 'due_at' => $now]);
        $this->loginManager();

        $all = $this->get(route('manager.index'))->assertOk()
            ->assertViewHas('active', 4)->assertViewHas('dueToday', 1)
            ->assertViewHas('dueTomorrow', 1)->assertViewHas('overdue', 1);
        $this->assertCount(1, $all->viewData('todayLoans'));
        $this->assertCount(1, $all->viewData('onTimeReturns'));

        $this->get(route('manager.index', ['prets' => 'retard']))->assertOk()
            ->assertViewHas('filter', 'retard')->assertDontSee('Rendus à temps ·');
        $this->get(route('manager.index', ['prets' => 'rendus']))->assertOk()
            ->assertViewHas('filter', 'rendus')->assertSee('Rendus à temps · 1')
            ->assertDontSee('À traiter ·');
    }

    public function test_student_detail_contains_only_current_overdue_copies(): void
    {
        $this->loginManager();

        $response = $this->get(route('manager.overdue.show', $this->hamzaId))->assertOk()
            ->assertSee('hamza.tazi5@usmba.ac.ma')
            ->assertSee('copy:DEMO-ALG-01')->assertSee('copy:DEMO-BDD-01')
            ->assertDontSee('copy:DEMO-WEB-01')->assertDontSee('copy:DEMO-BDD-02');

        $this->assertCount(2, $response->viewData('report')->loans);
        $this->assertSame(11, $response->viewData('report')->maxDaysLate);
        $this->assertSame('medium', $response->viewData('report')->level->value);
    }

    public function test_returned_or_cancelled_loans_disappear_from_the_dashboard(): void
    {
        $this->loginManager();
        $loans = Loan::overdue()->where('student_id', $this->hamzaId)->get();
        $loans[0]->update(['returned_at' => now()]);
        $loans[1]->update(['cancelled_at' => now()]);

        $response = $this->get(route('manager.index'))->assertOk()->assertDontSee('hamza.tazi5@usmba.ac.ma');
        $this->assertSame(1, $response->viewData('lateStudentCount'));
        $this->assertSame(1, $response->viewData('overdue'));
        $this->get(route('manager.overdue.show', $this->hamzaId))->assertNotFound();
    }

    public function test_due_at_the_current_instant_is_not_late_but_next_second_is(): void
    {
        Loan::overdue()->update(['returned_at' => now()]);
        $loan = Loan::whereNull('returned_at')->whereNull('cancelled_at')->firstOrFail();
        $loan->update(['due_at' => now()]);
        $this->loginManager();

        $this->get(route('manager.index'))->assertOk()->assertViewHas('overdue', 0)->assertViewHas('dueToday', 1);
        $this->travel(1)->seconds();
        $response = $this->get(route('manager.index'))->assertOk();
        $response->assertViewHas('lateStudentCount', 1)->assertViewHas('overdue', 1)->assertViewHas('longest', 1)->assertViewHas('dueToday', 0);
    }

    public function test_students_without_overdue_loans_and_unknown_students_have_no_overdue_page(): void
    {
        $this->loginManager();
        $studentId = User::where('email', 'aya.idrissi4@usmba.ac.ma')->value('id');

        $this->get(route('manager.overdue.show', $studentId))->assertNotFound();
        $this->get(route('manager.overdue.show', 999))->assertNotFound();
        $this->get(route('manager.overdue.show', $this->managerId))->assertNotFound();
    }

    public function test_guests_and_students_cannot_access_manager_reports(): void
    {
        $this->get(route('manager.index'))->assertRedirect(route('auth.google.redirect'));
        $this->get(route('manager.overdue.show', $this->hamzaId))->assertRedirect(route('auth.google.redirect'));
        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => $this->hamzaId]);

        $this->get(route('manager.index'))->assertForbidden();
        $this->get(route('manager.overdue.show', $this->hamzaId))->assertForbidden();
    }

    public function test_report_uses_the_directory_for_names_and_excludes_ineligible_identities(): void
    {
        $salmaId = User::where('email', 'salma.elalami2@usmba.ac.ma')->value('id');
        $directory = Mockery::mock(UserDirectory::class);
        $directory->shouldReceive('findByIds')->once()->andReturn([
            $this->hamzaId => new DirectoryUser($this->hamzaId, 'Identité API', 'hamza.tazi5@usmba.ac.ma', UserRole::Student, null),
            $salmaId => new DirectoryUser($salmaId, 'Ancien étudiant', 'external@gmail.com', UserRole::Student, null),
        ]);
        $this->app->instance(UserDirectory::class, $directory);

        $report = app(OverdueReport::class)->dashboard();

        $this->assertSame(1, $report['statistics']['students']);
        $this->assertSame('Identité API', $report['students'][0]->student->name);
        $this->assertSame(2, $report['statistics']['copies']);
    }

    public function test_imported_names_and_copy_details_are_escaped(): void
    {
        $payload = '<script>alert(1)</script>';
        User::findOrFail($this->hamzaId)->update(['name' => $payload]);
        $loan = Loan::overdue()->where('student_id', $this->hamzaId)->firstOrFail();
        $loan->copy->book->update(['title' => $payload]);
        $loan->copy->update(['qr_code' => $payload]);
        $this->loginManager();

        $this->get(route('manager.index'))->assertOk()->assertSee(e($payload), false)->assertDontSee($payload, false);
        $this->get(route('manager.overdue.show', $this->hamzaId))->assertOk()
            ->assertSee(e($payload), false)->assertDontSee($payload, false);
    }

    private function loginManager(): void
    {
        $this->withSession([Auth::guard()->getName() => $this->managerId]);
    }
}

