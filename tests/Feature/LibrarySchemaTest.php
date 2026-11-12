<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibrarySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_google_identity_fields_exist_and_seeding_adds_no_users(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['name', 'email', 'role', 'student_qr_code']));
        $this->assertFalse(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));

        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_third_role_is_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert(['name' => 'Test', 'email' => 'test@example.test', 'role' => 'admin']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);

        User::factory()->create(['email' => $user->email]);
    }

    public function test_duplicate_student_qr_is_rejected(): void
    {
        $student = User::factory()->create();
        $this->expectException(QueryException::class);

        User::factory()->create(['student_qr_code' => $student->student_qr_code]);
    }

    public function test_several_managers_can_have_no_student_qr(): void
    {
        User::factory()->manager()->count(2)->create();

        $this->assertDatabaseCount('users', 2);
    }

    public function test_duplicate_copy_qr_is_rejected(): void
    {
        $copy = $this->createCopy();
        $this->expectException(QueryException::class);

        Copy::create(['book_id' => $copy->book_id, 'qr_code' => $copy->qr_code]);
    }

    public function test_a_copy_cannot_reference_an_unknown_book(): void
    {
        $this->expectException(QueryException::class);

        Copy::create(['book_id' => 999, 'qr_code' => 'copy:missing']);
    }

    public function test_a_second_active_loan_for_the_same_copy_is_rejected(): void
    {
        $copy = $this->createCopy();
        $this->createLoan($copy);
        $this->expectException(QueryException::class);

        $this->createLoan($copy);
    }

    public function test_returned_and_cancelled_loans_allow_reborrowing_without_losing_history(): void
    {
        $copy = $this->createCopy();
        $returned = $this->createLoan($copy);
        $returned->update(['returned_at' => now()]);
        $cancelled = $this->createLoan($copy);
        $cancelled->update(['cancelled_at' => now()]);
        $active = $this->createLoan($copy);

        $this->assertDatabaseCount('loans', 3);
        $this->assertNull($returned->fresh()->active_copy_id);
        $this->assertNull($cancelled->fresh()->active_copy_id);
        $this->assertEquals($copy->id, $active->fresh()->active_copy_id);
    }

    public function test_referenced_copies_cannot_be_deleted(): void
    {
        $copy = $this->createCopy();
        $this->createLoan($copy);
        $this->expectException(QueryException::class);

        $copy->delete();
    }

    private function createCopy(): Copy
    {
        $book = Book::create([
            'title' => 'Algorithmique',
            'author' => 'Auteur test',
            'summary' => 'Résumé.',
            'table_of_contents' => 'Sommaire.',
            'cover_image' => 'https://example.test/cover.jpg',
        ]);

        return Copy::create(['book_id' => $book->id, 'qr_code' => 'copy:test']);
    }

    private function createLoan(Copy $copy): Loan
    {
        return Loan::create([
            'student_id' => User::factory()->create()->id,
            'copy_id' => $copy->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(config('library.loan_duration_days')),
        ]);
    }
}
