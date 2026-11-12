<?php

namespace Tests\Feature;

use App\Enums\CopyStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->loginAs(User::factory()->create(['role' => 'manager']));
    }

    public function test_isbn_preview_requires_confirmation_and_preserves_inventory_boundary(): void
    {
        config(['services.open_library.contact' => 'catalogue@example.test']);
        Http::fake([
            'openlibrary.org/isbn/0132350882.json' => Http::response($this->edition() + [
                'subtitle' => 'A Handbook',
                'publishers' => ['Prentice Hall'],
                'publish_date' => '2008',
                'edition_name' => 'First edition',
                'languages' => [['key' => '/languages/eng']],
                'covers' => [-1, 42],
                'works' => [['key' => '/works/OL10W']],
            ]),
            'openlibrary.org/works/OL10W.json' => Http::response([
                'description' => ['value' => 'Description of the work.'],
                'subjects' => ['Programming'],
                'authors' => [['author' => ['key' => '/authors/OL12A']]],
                'covers' => [99],
            ]),
            'openlibrary.org/authors/OL12A.json' => Http::response(['name' => 'Robert C. Martin']),
        ]);

        $url = route('manager.books.create', ['mode' => 'isbn', 'q' => '0-13-235088-2']);
        $preview = $this->get($url)->assertOk()->assertSee('Vérifier la fiche avant enregistrement')
            ->assertSee('D’après la notice de l’œuvre');
        $this->assertSame('9780132350884', $preview->viewData('metadata')['isbn']);
        $this->assertSame('Robert C. Martin', $preview->viewData('metadata')['author']);
        $this->assertDatabaseCount('books', 0);
        $this->get($url)->assertOk();
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent', 'ESTheque (catalogue@example.test)'));

        $token = session('open_library_preview.token');
        $this->from($url)->post(route('manager.books.store'), [
            'title' => 'Clean Code', 'isbn' => '9780306406157', 'preview_token' => $token,
        ])->assertSessionHasErrors('isbn');

        $this->get($url)->assertOk()->assertViewHas('token', $token);
        $this->post(route('manager.books.store'), [
            ...$preview->viewData('metadata'), 'subtitle' => 'Corrected subtitle', 'preview_token' => $token,
            'open_library_cover' => 99,
        ])->assertRedirect();

        $book = Book::sole();
        $this->assertSame('Corrected subtitle', $book->subtitle);
        $this->assertSame('Prentice Hall', $book->publisher);
        $this->assertSame('2008', $book->publication_date);
        $this->assertSame('First edition', $book->edition_statement);
        $this->assertSame('eng', $book->language);
        $this->assertSame('Programming', $book->subjects);
        $this->assertSame('OL10W', $book->open_library_work);
        $this->assertSame('https://openlibrary.org/books/OL11M', $book->open_library_source_url);
        $this->assertDatabaseCount('copies', 0);
        $this->assertDatabaseCount('loans', 0);

        $this->get($url)->assertOk()->assertSee('déjà enregistrée');
        $this->post(route('manager.books.store'), ['title' => 'Duplicate', 'isbn' => '0132350882'])
            ->assertRedirect(route('manager.books.show', $book));
        $this->assertDatabaseCount('books', 1);

        $this->loginAs(User::factory()->create());
        $this->get(route('student.index'))->assertOk()->assertSee('Clean Code')->assertSee('Indisponible')
            ->assertSee($book->coverUrl('M'));
        $this->get(route('student.books.show', $book))->assertOk()->assertSee($book->coverUrl('L'))
            ->assertSee('0 exemplaires disponibles sur 0');
        $this->get(route('student.index', ['filter' => 'available']))->assertOk()->assertDontSee('Clean Code');
        $book->copies()->create(['qr_code' => 'copy:open-library-test', 'status' => CopyStatus::Available]);
        $this->get(route('student.index', ['filter' => 'available']))->assertOk()->assertSee('Clean Code');
        $this->get(route('student.books.show', $book))->assertOk()->assertSee('1 exemplaire disponible sur 1');
        Http::assertSentCount(3);
    }

    public function test_title_and_author_search_allow_selection_of_a_specific_partial_edition(): void
    {
        Http::fake([
            'openlibrary.org/search.json*' => Http::response(['docs' => [['key' => '/works/OL10W', 'title' => 'Clean Code']]]),
            'openlibrary.org/works/OL10W/editions.json*' => Http::response(['size' => 2, 'entries' => [
                $this->edition(),
                ['key' => '/books/OL13M', 'title' => 'Another edition'],
            ]]),
            'openlibrary.org/books/OL11M.json' => Http::response($this->edition()),
        ]);

        foreach (['title' => 'Clean Code', 'author' => 'Robert C. Martin'] as $mode => $query) {
            $this->get(route('manager.books.create', ['mode' => $mode, 'q' => $query]))
                ->assertOk()->assertSee('Clean Code');
            Http::assertSent(fn (Request $request) => ($request->data()[$mode] ?? null) === $query
                && ($request->data()['limit'] ?? null) == 8);
        }

        $this->get(route('manager.books.create', ['work' => 'OL10W']))->assertOk()
            ->assertSee('Choisir une édition')->assertSee('Another edition');

        $url = route('manager.books.create', ['edition' => 'OL11M', 'work' => 'OL10W']);
        $preview = $this->get($url)->assertOk()->assertDontSee('Aucun résultat.')
            ->assertDontSee('covers.openlibrary.org', false);
        $this->assertNull($preview->viewData('metadata')['publisher']);
        $this->assertSame('9780132350884', $preview->viewData('metadata')['isbn']);
        $this->post(route('manager.books.store'), [
            'title' => 'Clean Code', 'isbn' => '9780132350884', 'preview_token' => $preview->viewData('token'),
        ])->assertRedirect(route('manager.books.show', Book::sole()));
        $this->assertNull(Book::sole()->coverUrl());
        $this->get($url)->assertOk()->assertSee('déjà enregistrée');
    }

    public function test_invalid_unknown_and_unavailable_lookups_leave_manual_entry_available(): void
    {
        Http::fake([
            'openlibrary.org/isbn/9780132350884.json' => Http::response([], 404),
            'openlibrary.org/isbn/9780306406157.json' => Http::failedConnection('Timeout'),
            'openlibrary.org/search.json*' => Http::response([], 503),
            'openlibrary.org/books/OL99M.json' => Http::response($this->edition()),
        ]);

        $this->from(route('manager.books.create'))->get(route('manager.books.create', ['mode' => 'isbn', 'q' => '9780132350885']))
            ->assertSessionHasErrors('q');
        Http::assertNothingSent();

        $missing = route('manager.books.create', ['mode' => 'isbn', 'q' => '9780132350884']);
        $this->get($missing)->assertOk()->assertSee('Édition introuvable')->assertSee('Saisie manuelle');
        $this->get($missing)->assertOk();
        Http::assertSentCount(1);
        $this->get(route('manager.books.create', ['mode' => 'isbn', 'q' => '9780306406157']))
            ->assertOk()->assertSee('ne répond pas');
        $this->get(route('manager.books.create', ['mode' => 'title', 'q' => 'Clean Code']))
            ->assertOk()->assertSee('momentanément indisponible');
        $this->get(route('manager.books.create', ['edition' => 'OL99M']))
            ->assertOk()->assertSee('Impossible de confirmer cette édition');

        $this->post(route('manager.books.store'), ['title' => 'Notice manuelle', 'open_library_edition' => 'OL99M'])
            ->assertRedirect();
        $this->assertNull(Book::sole()->open_library_edition);
    }

    public function test_rate_limit_response_pauses_external_requests_and_students_cannot_import(): void
    {
        Http::fake(['openlibrary.org/search.json*' => Http::response([], 429, ['Retry-After' => '90'])]);
        foreach (['One', 'Two'] as $query) {
            $this->get(route('manager.books.create', ['mode' => 'title', 'q' => $query]))
                ->assertOk()->assertSee('limite temporairement');
        }
        Http::assertSentCount(1);

        $this->loginAs(User::factory()->create());
        $this->get(route('manager.books.create'))->assertForbidden();
        $this->post(route('manager.books.store'), ['title' => 'Unauthorized'])->assertForbidden();
        $this->assertDatabaseCount('books', 0);
    }


    public function test_stock_import_is_idempotent_and_does_not_create_copies(): void
    {
        Http::fake(['openlibrary.org/isbn/0132350882.json' => Http::response($this->edition())]);
        $path = tempnam(sys_get_temp_dir(), 'stock-isbn-');
        try {
            file_put_contents($path, "0-13-235088-2\n");
            $this->artisan('library:import-stock-books', ['--file' => $path])->assertSuccessful();
            $this->artisan('library:import-stock-books', ['--file' => $path])->assertSuccessful();

            $this->assertSame('9780132350884', Book::sole()->isbn);
            $this->assertDatabaseCount('copies', 0);
            $this->assertDatabaseCount('loans', 0);
            Http::assertSentCount(1);
        } finally {
            unlink($path);
        }
    }

    private function edition(): array
    {
        return ['key' => '/books/OL11M', 'title' => 'Clean Code', 'isbn_13' => ['9780132350884']];
    }

    private function loginAs(User $user): void
    {
        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => $user->id]);
    }
}


