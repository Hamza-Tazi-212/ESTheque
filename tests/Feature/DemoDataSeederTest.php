<?php

namespace Tests\Feature;

use App\Enums\CopyStatus;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\User;
use App\Support\AcademicEmail;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_contains_both_roles_and_consistent_circulation_examples(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('books', 4);
        $this->assertDatabaseCount('copies', 12);
        $this->assertDatabaseCount('loans', 6);
        $this->assertDatabaseHas('users', ['email' => 'hamza.tazi5@usmba.ac.ma', 'role' => 'student']);
        $this->assertDatabaseHas('users', ['email' => 'nabil.amrani1@usmba.ac.ma', 'role' => 'manager', 'student_qr_code' => null]);

        foreach (User::all() as $user) {
            $this->assertTrue(AcademicEmail::isValid($user->email));
        }

        $active = Loan::whereNull('returned_at')->whereNull('cancelled_at')->get();
        $this->assertCount(4, $active);
        $this->assertSame(3, $active->groupBy('student_id')->map->count()->max());

        $lateDays = $active->filter(fn (Loan $loan) => $loan->due_at->isPast())
            ->map(fn (Loan $loan) => (int) $loan->due_at->diffInDays(now()))->sort()->values()->all();
        $this->assertSame([1, 11, 21], $lateDays);

        foreach (Copy::all() as $copy) {
            $expected = $active->contains('copy_id', $copy->id) ? CopyStatus::Borrowed : CopyStatus::Available;
            $this->assertSame($expected, $copy->status);
        }

        $this->assertSame(1, Loan::whereNotNull('returned_at')->count());
        $this->assertSame(1, Loan::whereNotNull('cancelled_at')->count());
    }

    public function test_demo_due_dates_and_overdue_examples_use_the_configured_duration(): void
    {
        $this->travelTo(now()->startOfSecond());
        config(['library.loan_duration_days' => 60]);
        $this->seed(DemoDataSeeder::class);

        foreach (Loan::all() as $loan) {
            $this->assertSame(60, (int) $loan->borrowed_at->diffInDays($loan->due_at));
        }

        $lateDays = Loan::whereNull('returned_at')->whereNull('cancelled_at')->where('due_at', '<', now())
            ->get()->map(fn (Loan $loan) => (int) $loan->due_at->diffInDays(now()))->sort()->values()->all();
        $this->assertSame([1, 21, 41], $lateDays);
    }

    public function test_demo_seeding_refuses_existing_data_without_overwriting_it(): void
    {
        $existing = User::factory()->create(['name' => 'Données à conserver']);

        try {
            $this->seed(DemoDataSeeder::class);
            $this->fail('Seeding should refuse a populated database.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('base contient déjà', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Données à conserver', $existing->fresh()->name);
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_demo_seeding_is_refused_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(LogicException::class);
        app(DemoDataSeeder::class)->run();
    }
}
