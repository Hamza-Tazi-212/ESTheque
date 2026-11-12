<?php

namespace Tests\Feature;

use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Enums\UserRole;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\User;
use App\Services\CirculationService;
use App\Services\OverdueReport;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class QrCirculationTest extends TestCase
{
    use RefreshDatabase;

    private int $managerId;

    private int $ayaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->startOfSecond());
        $this->seed(DemoDataSeeder::class);
        $this->managerId = User::where('role', 'manager')->value('id');
        $this->ayaId = User::where('email', 'aya.idrissi4@usmba.ac.ma')->value('id');
        $this->withSession([Auth::guard()->getName() => $this->managerId]);
        Mail::fake();
        Queue::fake();
    }

    public function test_scan_page_has_camera_controls_without_manual_entry_or_file_import(): void
    {
        $this->get(route('manager.scan'))->assertOk()->assertSee('Scanner les QR')
            ->assertSee('Activer la caméra')->assertSee('Annuler cet emprunt')
            ->assertSee('csrf-token', false)->assertDontSee('type="file"', false)
            ->assertDontSee('type="text"', false);
    }

    public function test_identification_accepts_existing_opaque_ids_without_required_prefixes_and_is_read_only(): void
    {
        User::findOrFail($this->ayaId)->update(['student_qr_code' => 'opaque-card-8765']);
        $copy = Copy::where('qr_code', 'copy:DEMO-ALG-02')->firstOrFail();
        $copy->update(['qr_code' => '012345-BOOK']);

        $this->postJson(route('manager.scan.identify'), ['qr_code' => 'opaque-card-8765'])
            ->assertOk()->assertJsonPath('type', 'student')->assertJsonPath('label', 'Aya Idrissi — démo');
        $this->postJson(route('manager.scan.identify'), ['qr_code' => '012345-BOOK'])
            ->assertOk()->assertJsonPath('type', 'copy');
        $this->assertDatabaseCount('loans', 6);
        $this->assertDatabaseCount('scan_operations', 0);
    }

    public function test_qr_identifiers_are_not_trimmed_or_interpreted_as_urls(): void
    {
        $qr = '  https://example.test/card?key=123  ';
        User::findOrFail($this->ayaId)->update(['student_qr_code' => $qr]);

        $this->postJson(route('manager.scan.identify'), ['qr_code' => $qr])
            ->assertOk()->assertJsonPath('type', 'student')->assertJsonPath('qr_code', $qr);
        $this->scan([$qr, 'copy:DEMO-ALG-02'])->assertOk()->assertJsonPath('operation', 'borrowed');
    }

    #[DataProvider('loanDurations')]
    public function test_two_qrs_automatically_borrow_with_the_configured_due_date(int $duration): void
    {
        config(['library.loan_duration_days' => $duration]);
        $response = $this->scan(['copy:DEMO-ALG-02', 'student:DEMO-004'])
            ->assertOk()->assertJsonPath('operation', 'borrowed')
            ->assertJsonPath('student.id', $this->ayaId)
            ->assertJsonPath('due_at', now()->addDays($duration)->toIso8601String());

        $this->assertDatabaseCount('loans', 7);
        $this->assertDatabaseHas('copies', ['qr_code' => 'copy:DEMO-ALG-02', 'status' => 'borrowed']);
        $this->assertDatabaseHas('loans', ['id' => $response->json('loan_id'), 'student_id' => $this->ayaId, 'returned_at' => null, 'cancelled_at' => null]);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public static function loanDurations(): array
    {
        return [[30], [60]];
    }

    public function test_same_pair_in_either_order_returns_then_allows_a_later_borrow(): void
    {
        $borrow = $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'])->assertOk();
        $this->scan(['copy:DEMO-ALG-02', 'student:DEMO-004'])->assertOk()->assertJsonPath('operation', 'returned');
        $this->assertDatabaseHas('loans', ['id' => $borrow->json('loan_id'), 'returned_at' => now()->toDateTimeString()]);
        $this->assertDatabaseHas('copies', ['qr_code' => 'copy:DEMO-ALG-02', 'status' => 'available']);
        $again = $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'])->assertOk()->assertJsonPath('operation', 'borrowed');

        $this->assertNotSame($borrow->json('loan_id'), $again->json('loan_id'));
        $this->assertDatabaseCount('loans', 8);
    }

    public function test_replayed_request_with_reversed_qr_order_does_not_turn_a_borrow_into_a_return(): void
    {
        $requestId = (string) Str::uuid();
        $first = $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'], $requestId)->assertOk();
        $this->scan(['copy:DEMO-ALG-02', 'student:DEMO-004'], $requestId)->assertOk()->assertExactJson($first->json());

        $this->assertDatabaseCount('loans', 7);
        $this->assertDatabaseCount('scan_operations', 1);
        $this->assertDatabaseHas('loans', ['id' => $first->json('loan_id'), 'returned_at' => null]);
    }

    public function test_a_replayed_return_does_not_start_another_borrow(): void
    {
        $requestId = (string) Str::uuid();
        $first = $this->scan(['student:DEMO-002', 'copy:DEMO-RES-01'], $requestId)
            ->assertOk()->assertJsonPath('operation', 'returned');
        $this->scan(['copy:DEMO-RES-01', 'student:DEMO-002'], $requestId)
            ->assertOk()->assertExactJson($first->json());

        $this->assertDatabaseCount('loans', 6);
        $this->assertDatabaseHas('copies', ['qr_code' => 'copy:DEMO-RES-01', 'status' => 'available']);
    }

    public function test_a_fourth_copy_is_refused_but_a_return_is_allowed_at_the_limit(): void
    {
        $this->scan(['student:DEMO-001', 'copy:DEMO-ALG-02'])->assertUnprocessable()
            ->assertJsonPath('error', 'loan_limit')->assertSee('3 exemplaires');
        $this->assertDatabaseCount('loans', 6);
        $this->assertDatabaseHas('copies', ['qr_code' => 'copy:DEMO-ALG-02', 'status' => 'available']);

        $this->scan(['student:DEMO-001', 'copy:DEMO-ALG-01'])->assertOk()->assertJsonPath('operation', 'returned');
        $this->scan(['student:DEMO-001', 'copy:DEMO-ALG-02'])->assertOk()->assertJsonPath('operation', 'borrowed');
    }

    public function test_refused_request_keeps_its_result_when_replayed_after_the_limit_changes(): void
    {
        $requestId = (string) Str::uuid();
        $this->scan(['student:DEMO-001', 'copy:DEMO-ALG-02'], $requestId)->assertUnprocessable();
        $this->scan(['student:DEMO-001', 'copy:DEMO-ALG-01'])->assertOk();

        $this->scan(['student:DEMO-001', 'copy:DEMO-ALG-02'], $requestId)
            ->assertUnprocessable()->assertJsonPath('error', 'loan_limit');
        $this->assertDatabaseCount('loans', 6);
    }

    public function test_another_borrowers_copy_is_refused_without_changing_the_loan(): void
    {
        $this->scan(['student:DEMO-004', 'copy:DEMO-RES-01'])->assertUnprocessable()->assertJsonPath('error', 'another_borrower');

        $this->assertDatabaseHas('copies', ['qr_code' => 'copy:DEMO-RES-01', 'status' => 'borrowed']);
        $this->assertSame(4, Loan::whereNull('returned_at')->whereNull('cancelled_at')->count());
        $this->assertDatabaseCount('loans', 6);
    }

    public function test_return_updates_catalogue_availability_and_removes_the_student_from_overdue_reports(): void
    {
        $salmaId = User::where('email', 'salma.elalami2@usmba.ac.ma')->value('id');
        $book = Copy::where('qr_code', 'copy:DEMO-RES-01')->firstOrFail()->book;
        $before = $book->copies()->where('status', 'available')->count();

        $this->scan(['student:DEMO-002', 'copy:DEMO-RES-01'])->assertOk()->assertJsonPath('operation', 'returned');

        $this->assertSame($before + 1, $book->copies()->where('status', 'available')->count());
        $this->assertNull(app(OverdueReport::class)->forStudent($salmaId));
        $this->get(route('manager.index'))->assertOk()->assertDontSee('salma.elalami2@usmba.ac.ma');
        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => $this->ayaId]);
        $this->get(route('student.books.show', $book))->assertOk()->assertSee('3 exemplaires disponibles sur 3');
    }

    public function test_cancelling_a_borrow_releases_the_copy_and_frees_the_student_slot(): void
    {
        $borrow = $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'])->assertOk();
        $this->cancel($borrow->json('loan_id'))->assertOk()->assertJsonPath('operation', 'cancelled');

        $this->assertDatabaseHas('loans', ['id' => $borrow->json('loan_id'), 'cancelled_at' => now()->toDateTimeString(), 'returned_at' => null]);
        $this->assertDatabaseHas('copies', ['qr_code' => 'copy:DEMO-ALG-02', 'status' => 'available']);
        $this->assertSame(0, Loan::where('student_id', $this->ayaId)->whereNull('returned_at')->whereNull('cancelled_at')->count());
    }

    public function test_old_cancellation_or_scan_replay_cannot_release_a_new_borrow(): void
    {
        $scanId = (string) Str::uuid();
        $cancelId = (string) Str::uuid();
        $original = $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'], $scanId)->assertOk();
        $cancelled = $this->cancel($original->json('loan_id'), $cancelId)->assertOk();
        $new = $this->scan(['student:DEMO-003', 'copy:DEMO-ALG-02'])->assertOk();

        $this->cancel($original->json('loan_id'), $cancelId)->assertOk()->assertExactJson($cancelled->json());
        $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'], $scanId)->assertOk()->assertExactJson($original->json());
        $this->cancel($original->json('loan_id'))->assertConflict()->assertJsonPath('error', 'loan_closed');
        $this->assertDatabaseHas('loans', ['id' => $new->json('loan_id'), 'returned_at' => null, 'cancelled_at' => null]);
        $this->assertDatabaseHas('copies', ['qr_code' => 'copy:DEMO-ALG-02', 'status' => 'borrowed']);
    }

    public function test_returned_or_unknown_loans_cannot_be_cancelled(): void
    {
        $returned = Loan::whereNotNull('returned_at')->firstOrFail();
        $this->cancel($returned->id)->assertConflict()->assertJsonPath('error', 'loan_closed');
        $this->cancel(999)->assertNotFound()->assertJsonPath('error', 'loan_not_found');
    }

    public function test_unknown_ambiguous_and_non_academic_cards_are_rejected(): void
    {
        $this->postJson(route('manager.scan.identify'), ['qr_code' => 'unknown'])->assertUnprocessable()->assertJsonPath('error', 'unknown_qr');
        User::findOrFail($this->ayaId)->update(['student_qr_code' => 'copy:DEMO-ALG-02']);
        $this->postJson(route('manager.scan.identify'), ['qr_code' => 'copy:DEMO-ALG-02'])->assertUnprocessable()->assertJsonPath('error', 'ambiguous_qr');
        User::findOrFail($this->ayaId)->update(['student_qr_code' => 'student:DEMO-004', 'email' => 'external@gmail.com']);
        $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'])->assertUnprocessable()->assertJsonPath('error', 'invalid_student');
        $this->assertDatabaseCount('loans', 6);
    }

    public function test_two_student_cards_or_two_copy_qrs_are_not_a_valid_pair(): void
    {
        $this->scan(['student:DEMO-003', 'student:DEMO-004'])->assertUnprocessable()->assertJsonPath('error', 'invalid_pair');
        $this->scan(['copy:DEMO-ALG-02', 'copy:DEMO-ALG-03'])->assertUnprocessable()->assertJsonPath('error', 'invalid_pair');
        $this->assertDatabaseCount('loans', 6);
    }

    public function test_invalid_payloads_never_create_an_operation(): void
    {
        foreach ([
            ['qr_codes' => ['student:DEMO-004', 'copy:DEMO-ALG-02']],
            ['request_id' => 'invalid', 'qr_codes' => ['student:DEMO-004', 'copy:DEMO-ALG-02']],
            ['request_id' => (string) Str::uuid(), 'qr_codes' => ['student:DEMO-004']],
            ['request_id' => (string) Str::uuid(), 'qr_codes' => ['student:DEMO-004', 'student:DEMO-004']],
            ['request_id' => (string) Str::uuid(), 'qr_codes' => ['student:DEMO-004', str_repeat('x', 192)]],
            ['request_id' => (string) Str::uuid(), 'qr_codes' => ['first' => 'student:DEMO-004', 'second' => 'copy:DEMO-ALG-02']],
        ] as $body) {
            $this->postJson(route('manager.scan.store'), $body)->assertUnprocessable();
        }
        $this->assertDatabaseCount('loans', 6);
        $this->assertDatabaseCount('scan_operations', 0);
    }

    public function test_a_request_key_cannot_be_reused_for_a_different_pair_or_another_manager(): void
    {
        $key = (string) Str::uuid();
        $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'], $key)->assertOk();
        $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-03'], $key)->assertConflict()->assertJsonPath('error', 'request_conflict');
        $manager = User::factory()->manager()->create();
        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => $manager->id]);

        $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'], $key)->assertConflict()
            ->assertJsonPath('error', 'request_conflict')->assertJsonMissingPath('student');
        $this->assertDatabaseCount('loans', 7);
        $this->assertDatabaseCount('scan_operations', 1);
    }

    public function test_inconsistent_imported_copy_states_are_refused_without_being_silently_repaired(): void
    {
        Copy::where('qr_code', 'copy:DEMO-ALG-01')->update(['status' => 'available']);
        Copy::where('qr_code', 'copy:DEMO-ALG-02')->update(['status' => 'borrowed']);

        $this->scan(['student:DEMO-001', 'copy:DEMO-ALG-01'])->assertConflict()->assertJsonPath('error', 'inconsistent_copy');
        $this->scan(['student:DEMO-004', 'copy:DEMO-ALG-02'])->assertConflict()->assertJsonPath('error', 'inconsistent_copy');
        $this->assertDatabaseCount('loans', 6);
    }

    public function test_student_identity_is_resolved_through_the_directory(): void
    {
        $directory = Mockery::mock(UserDirectory::class);
        $directory->shouldReceive('findStudentByQrCode')->with('remote-card')->once()->andReturn(
            new DirectoryUser($this->ayaId, 'Étudiant API', 'api.student1@usmba.ac.ma', UserRole::Student, 'remote-card')
        );
        $directory->shouldReceive('findStudentByQrCode')->with('copy:DEMO-ALG-02')->once()->andReturn(null);
        $this->app->instance(UserDirectory::class, $directory);

        $result = app(CirculationService::class)->scan($this->managerId, (string) Str::uuid(), ['remote-card', 'copy:DEMO-ALG-02']);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Étudiant API', $result['body']['student']['name']);
        $this->assertDatabaseHas('loans', ['student_id' => $this->ayaId, 'returned_at' => null, 'cancelled_at' => null]);
    }

    #[DataProvider('mutationKinds')]
    public function test_storage_failure_rolls_back_the_loan_copy_and_request_record(string $kind): void
    {
        $beforeLoans = DB::table('loans')->orderBy('id')->get()->toJson();
        $beforeCopies = DB::table('copies')->orderBy('id')->get()->toJson();
        Copy::updated(fn () => throw new RuntimeException('Simulated storage failure'));

        try {
            $service = app(CirculationService::class);
            if ($kind === 'cancel') {
                $copyId = Copy::where('qr_code', 'copy:DEMO-RES-01')->value('id');
                $service->cancel($this->managerId, (string) Str::uuid(), Loan::where('copy_id', $copyId)->value('id'));
            } else {
                $codes = $kind === 'borrow' ? ['student:DEMO-004', 'copy:DEMO-ALG-02'] : ['student:DEMO-002', 'copy:DEMO-RES-01'];
                $service->scan($this->managerId, (string) Str::uuid(), $codes);
            }
            $this->fail('A storage failure should propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated storage failure', $error->getMessage());
        } finally {
            Copy::flushEventListeners();
        }

        $this->assertSame($beforeLoans, DB::table('loans')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeCopies, DB::table('copies')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('scan_operations', 0);
    }

    public static function mutationKinds(): array
    {
        return [['borrow'], ['return'], ['cancel']];
    }

    public function test_guests_and_students_cannot_identify_borrow_return_or_cancel(): void
    {
        session()->forget(Auth::guard()->getName());
        Auth::forgetGuards();
        $this->get(route('manager.scan'))->assertRedirect(route('auth.google.redirect'));
        $body = ['request_id' => (string) Str::uuid(), 'qr_codes' => ['student:DEMO-004', 'copy:DEMO-ALG-02']];
        $this->postJson(route('manager.scan.identify'), ['qr_code' => 'student:DEMO-004'])->assertUnauthorized();
        $this->postJson(route('manager.scan.store'), $body)->assertUnauthorized();
        $this->postJson(route('manager.loans.cancel', 1), ['request_id' => (string) Str::uuid()])->assertUnauthorized();
        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => $this->ayaId]);
        $this->get(route('manager.scan'))->assertForbidden();
        $this->postJson(route('manager.scan.identify'), ['qr_code' => 'student:DEMO-004'])->assertForbidden();
        $this->postJson(route('manager.scan.store'), $body)->assertForbidden();
        $this->postJson(route('manager.loans.cancel', 1), ['request_id' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('scan_operations', 0);
    }

    public function test_scan_post_requests_require_a_valid_csrf_token_outside_testing_mode(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->get(route('manager.scan'))->assertOk();

        $this->postJson(route('manager.scan.identify'), ['qr_code' => 'student:DEMO-004'])->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', session()->token())
            ->postJson(route('manager.scan.identify'), ['qr_code' => 'student:DEMO-004'])
            ->assertOk()->assertJsonPath('type', 'student');

        $this->assertDatabaseCount('scan_operations', 0);
    }

    private function scan(array $qrCodes, ?string $requestId = null)
    {
        return $this->postJson(route('manager.scan.store'), ['request_id' => $requestId ?? (string) Str::uuid(), 'qr_codes' => $qrCodes]);
    }

    private function cancel(int $loanId, ?string $requestId = null)
    {
        return $this->postJson(route('manager.loans.cancel', $loanId), ['request_id' => $requestId ?? (string) Str::uuid()]);
    }
}
