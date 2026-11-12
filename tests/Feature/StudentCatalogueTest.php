<?php

namespace Tests\Feature;

use App\Enums\CopyStatus;
use App\Models\Book;
use App\Models\Copy;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_student_sees_compact_rows_with_book_dialog_including_unavailable_books(): void
    {
        $this->loginStudent();
        $available = $this->createBook(['title' => 'Algorithmique', 'author' => 'Auteur A']);
        $unavailable = $this->createBook(['title' => 'Réseaux', 'author' => 'Auteur B']);
        $this->createCopy($available);
        $this->createCopy($available, CopyStatus::Borrowed);
        $this->createCopy($unavailable, CopyStatus::Borrowed);

        $response = $this->get(route('student.index'))->assertOk()
            ->assertSeeInOrder(['Algorithmique', 'Auteur A', 'Disponible', 'Réseaux', 'Auteur B', 'Indisponible'])
            ->assertSee(route('student.books.show', $available), false);

        $xpath = $this->html($response->getContent());
        $this->assertSame(2, $xpath->query('//a[@data-book-details]')->length);
        $this->assertSame(1, $xpath->query('//dialog[not(@open)]')->length);
    }

    #[DataProvider('searches')]
    public function test_search_matches_title_or_author_and_handles_empty_input(string $search, array $titles): void
    {
        $this->loginStudent();
        $this->createBook(['title' => 'Algorithmique', 'author' => 'Auteur Alpha']);
        $this->createBook(['title' => 'Réseaux', 'author' => 'Auteur Beta']);

        $response = $this->get(route('student.index', ['q' => $search]))->assertOk();

        $this->assertSame($titles, $response->viewData('books')->pluck('title')->all());
        $this->assertSame(trim($search), $response->viewData('search'));
    }

    public static function searches(): array
    {
        return [
            'partial title and case' => ['ALGOR', ['Algorithmique']],
            'partial author' => ['beta', ['Réseaux']],
            'surrounding whitespace' => ['  Alpha  ', ['Algorithmique']],
            'empty input' => ['', ['Algorithmique', 'Réseaux']],
            'only whitespace' => ['  ', ['Algorithmique', 'Réseaux']],
            'no match' => ['Livre absent', []],
        ];
    }

    #[DataProvider('literalSearches')]
    public function test_search_treats_wildcards_and_sql_fragments_as_literal_text(string $search, array $titles): void
    {
        $this->loginStudent();
        foreach (['SQL à 100%', 'SQL_essentiel', 'SQL! pratique', 'SQL sans symbole'] as $title) {
            $this->createBook(['title' => $title]);
        }

        $response = $this->get(route('student.index', ['q' => $search]))->assertOk();

        $this->assertSame($titles, $response->viewData('books')->pluck('title')->all());
    }

    public static function literalSearches(): array
    {
        return [
            'percent' => ['%', ['SQL à 100%']],
            'underscore' => ['_', ['SQL_essentiel']],
            'escape character' => ['!', ['SQL! pratique']],
            'sql fragment' => ["' OR 1=1 --", []],
        ];
    }

    public function test_search_matches_summary_but_not_table_of_contents(): void
    {
        $this->loginStudent();
        $this->createBook(['summary' => 'Expression propre au résumé', 'table_of_contents' => 'Expression propre au sommaire']);

        $summary = $this->get(route('student.index', ['q' => 'propre au résumé']))->assertOk();
        $this->assertCount(1, $summary->viewData('books'));
        $this->get(route('student.index', ['q' => 'propre au sommaire']))
            ->assertOk()->assertSee('Aucun ouvrage trouvé');
    }

    public function test_empty_catalogue_and_no_search_result_have_distinct_messages(): void
    {
        $this->loginStudent();
        $this->get(route('student.index'))->assertOk()->assertSee('Le catalogue est vide pour le moment');
        $this->createBook();

        $this->get(route('student.index', ['q' => 'introuvable']))
            ->assertOk()->assertSee('Aucun ouvrage trouvé')->assertSee('Effacer la recherche');
    }

    public function test_invalid_searches_redirect_safely_with_a_french_error(): void
    {
        $this->loginStudent();

        $this->get(route('student.index', ['q' => ['invalid']]))
            ->assertRedirect(route('student.index'))->assertSessionHasErrors('q');
        $this->get(route('student.index'))->assertOk()->assertSee('Saisissez un titre ou un auteur');

        $this->get(route('student.index', ['q' => str_repeat('a', 256)]))
            ->assertRedirect(route('student.index'))->assertSessionHasErrors('q');
        $this->get(route('student.index'))->assertOk()->assertSee('255 caractères');
    }

    public function test_details_show_cover_summary_contents_and_search_context(): void
    {
        $this->loginStudent();
        $book = $this->createBook([
            'summary' => "Un petit résumé.\nDeuxième ligne.",
            'table_of_contents' => "1. Introduction\n2. Applications",
            'cover_image' => '/demo/cover.svg',
        ]);
        $this->createCopy($book);

        $response = $this->get(route('student.books.show', ['book' => $book, 'q' => 'Algorithmique']))
            ->assertOk()->assertSee($book->title)->assertSee($book->author)
            ->assertSee($book->summary)->assertSee($book->table_of_contents)
            ->assertSee('1 exemplaire disponible sur 1')
            ->assertSee(route('student.index', ['q' => 'Algorithmique']), false)
            ->assertSee('présentez-vous à la bibliothèque');

        $xpath = $this->html($response->getContent());
        $cover = $xpath->query('//img[@data-cover-image]')->item(0);
        $this->assertSame('/demo/cover.svg', $cover->getAttribute('src'));
        $this->assertSame('Couverture de '.$book->title, $cover->getAttribute('alt'));
    }

    public function test_a_book_without_copies_is_unavailable_on_both_pages(): void
    {
        $this->loginStudent();
        $book = $this->createBook();

        $this->get(route('student.index'))->assertOk()->assertSee($book->title)->assertSee('Indisponible');
        $this->get(route('student.books.show', $book))->assertOk()->assertSee('Indisponible')
            ->assertSee('0 exemplaires disponibles sur 0');
    }

    public function test_availability_reflects_copy_status_changes_on_the_next_read(): void
    {
        $this->loginStudent();
        $book = $this->createBook();
        $copy = $this->createCopy($book, CopyStatus::Borrowed);
        $this->get(route('student.books.show', $book))->assertOk()->assertSee('Indisponible');

        $copy->update(['status' => CopyStatus::Available]);

        $this->get(route('student.index'))->assertOk()->assertSee('Disponible');
        $available = $this->get(route('student.index', ['filter' => 'available']))->assertOk();
        $this->assertSame([$book->id], $available->viewData('books')->modelKeys());
        $this->get(route('student.books.show', $book))->assertOk()->assertSee('Disponible')
            ->assertSee('1 exemplaire disponible sur 1');
    }

    public function test_nonexistent_book_returns_404(): void
    {
        $this->loginStudent();

        $this->get(route('student.books.show', 999))->assertNotFound();
    }

    public function test_detail_page_redirects_guests_and_refuses_managers(): void
    {
        $book = $this->createBook();
        $this->get(route('student.books.show', $book))->assertRedirect(route('auth.google.redirect'));

        $manager = User::factory()->manager()->create();
        $this->withSession([Auth::guard()->getName() => $manager->id]);
        Auth::forgetGuards();

        $this->get(route('student.index'))->assertForbidden();
        $this->get(route('student.books.show', $book))->assertForbidden();
    }

    public function test_imported_content_and_search_input_are_html_escaped(): void
    {
        $this->loginStudent();
        $payload = '<script>alert(1)</script>';
        $book = $this->createBook([
            'title' => $payload, 'author' => $payload, 'summary' => $payload, 'table_of_contents' => $payload,
        ]);

        $this->get(route('student.index', ['q' => $payload]))->assertOk()
            ->assertSee(e($payload), false)->assertDontSee($payload, false);
        $this->get(route('student.books.show', $book))->assertOk()
            ->assertSee(e($payload), false)->assertDontSee($payload, false);
    }

    #[DataProvider('invalidCoverUrls')]
    public function test_invalid_cover_sources_show_a_fallback(string $source): void
    {
        $this->loginStudent();
        $book = $this->createBook(['cover_image' => $source]);

        $response = $this->get(route('student.books.show', $book))->assertOk()->assertSee('Couverture indisponible');

        $this->assertSame(0, $this->html($response->getContent())->query('//img[@data-cover-image]')->length);
    }

    public static function invalidCoverUrls(): array
    {
        return [
            'empty' => [''],
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'protocol relative' => ['//example.test/cover.jpg'],
            'backslash' => ['/\\example.test/cover.jpg'],
        ];
    }

    public function test_https_cover_source_is_accepted(): void
    {
        $this->loginStudent();
        $book = $this->createBook(['cover_image' => 'https://example.test/cover.jpg']);

        $response = $this->get(route('student.books.show', $book))->assertOk();
        $cover = $this->html($response->getContent())->query('//img[@data-cover-image]')->item(0);

        $this->assertSame('https://example.test/cover.jpg', $cover->getAttribute('src'));
    }

    public function test_catalogue_provides_no_write_routes_and_does_not_modify_library_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $student = User::where('email', 'hamza.tazi5@usmba.ac.ma')->firstOrFail();
        $this->withSession([Auth::guard()->getName() => $student->id]);
        $book = Book::firstOrFail();
        $tables = ['users', 'books', 'copies', 'loans'];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);

        $this->get(route('student.index'))->assertOk();
        $this->get(route('student.books.show', $book))->assertOk();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->call($method, route('student.books.show', $book))->assertStatus(405);
        }

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    private function loginStudent(): void
    {
        $student = User::factory()->create();
        $this->withSession([Auth::guard()->getName() => $student->id]);
    }

    private function createBook(array $attributes = []): Book
    {
        return Book::create([
            'title' => 'Algorithmique',
            'author' => 'Auteur de test',
            'summary' => 'Résumé de test.',
            'table_of_contents' => "1. Introduction\n2. Applications",
            'cover_image' => '/demo/cover.svg',
            ...$attributes,
        ]);
    }

    private function createCopy(Book $book, CopyStatus $status = CopyStatus::Available): Copy
    {
        return Copy::create(['book_id' => $book->id, 'qr_code' => 'copy:test:'.Str::uuid(), 'status' => $status]);
    }

    private function html(string $content): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
