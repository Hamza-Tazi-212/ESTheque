<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\OpenLibrary;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BookController extends Controller
{
    public function create(Request $request, OpenLibrary $library)
    {
        $input = $request->validate([
            'mode' => 'nullable|in:isbn,title,author',
            'q' => 'required_with:mode|nullable|string|max:150',
            'work' => 'nullable|regex:/^OL\d+W$/D|max:32',
            'edition' => 'nullable|regex:/^OL\d+M$/D|max:32',
            'page' => 'nullable|integer|min:1|max:100',
        ]);
        $mode = $input['mode'] ?? null;
        $query = $input['q'] ?? '';
        $work = $input['work'] ?? null;
        $page = (int) ($input['page'] ?? 1);
        $isbn = $mode === 'isbn' ? OpenLibrary::isbn($query) : null;
        if ($mode === 'isbn' && ! $isbn) {
            throw ValidationException::withMessages(['q' => 'Saisissez un ISBN-10 ou ISBN-13 valide.']);
        }

        $metadata = $results = [];
        $more = false;
        $notice = null;
        $existing = $isbn ? $this->duplicate($isbn, null) : null;
        $token = null;
        $preview = $request->session()->get('open_library_preview');

        try {
            if ($request->old('preview_token') && ($preview['token'] ?? null) === $request->old('preview_token')) {
                $token = $preview['token'];
                $metadata = $preview['metadata'];
            } elseif (! $existing && ($isbn || isset($input['edition']))) {
                $metadata = $isbn
                    ? ($library->edition(strtoupper(preg_replace('/[\s-]+/', '', $query)), $isbn) ?? [])
                    : ($library->edition($input['edition']) ?? []);
                if ($metadata) {
                    $existing = $this->duplicate($metadata['isbn'], $metadata['open_library_edition']);
                    if (! $existing) {
                        $token = Str::random(40);
                        $request->session()->put('open_library_preview', [
                            'token' => $token,
                            'metadata' => $metadata,
                        ]);
                    }
                } else {
                    $notice = 'Édition introuvable. Vous pouvez modifier la recherche ou remplir la fiche manuellement.';
                }
            } elseif (! $existing && $work) {
                [$results, $more] = $library->editions($work, $page);
            } elseif (! $existing && $mode) {
                $results = $library->search($mode, $query);
            }
        } catch (RuntimeException $exception) {
            $notice = $exception->getMessage();
        }

        return view('manager.books.create', compact('mode', 'query', 'work', 'page', 'more', 'results',
            'metadata', 'notice', 'existing', 'token'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:32',
            'subtitle' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'publication_date' => 'nullable|string|max:255',
            'edition_statement' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:255',
            'subjects' => 'nullable|string|max:12000',
            'summary' => 'nullable|string|max:12000',
            'table_of_contents' => 'nullable|string|max:12000',
            'preview_token' => 'nullable|string|size:40',
        ]);

        $enteredIsbn = $data['isbn'] ?? null;
        $data['isbn'] = $enteredIsbn ? OpenLibrary::isbn($enteredIsbn) : null;
        if ($enteredIsbn !== null && $data['isbn'] === null) {
            throw ValidationException::withMessages(['isbn' => 'Saisissez un ISBN-10 ou ISBN-13 valide.']);
        }

        $previewToken = $data['preview_token'] ?? null;
        unset($data['preview_token']);
        $preview = $request->session()->get('open_library_preview');
        if ($previewToken !== null) {
            if (($preview['token'] ?? null) !== $previewToken) {
                throw ValidationException::withMessages(['preview_token' => 'L’aperçu a expiré. Rechargez l’édition avant de l’enregistrer.']);
            }
            $metadata = $preview['metadata'];
            if ($metadata['isbn'] && $metadata['isbn'] !== $data['isbn']) {
                throw ValidationException::withMessages(['isbn' => 'Pour changer d’ISBN, sélectionnez l’édition correspondante ou utilisez la saisie manuelle.']);
            }
            foreach (['open_library_edition', 'open_library_work', 'open_library_cover'] as $field) {
                $data[$field] = $metadata[$field];
            }
        }
        $edition = $data['open_library_edition'] ?? null;
        if ($existing = $this->duplicate($data['isbn'], $edition)) {
            return redirect()->route('manager.books.show', $existing)->with('duplicate', true);
        }

        foreach (['author', 'summary', 'table_of_contents'] as $field) {
            $data[$field] ??= '';
        }
        $data['cover_image'] = '';

        try {
            $book = Book::create($data);
        } catch (UniqueConstraintViolationException $exception) {
            if ($existing = $this->duplicate($data['isbn'], $edition)) {
                return redirect()->route('manager.books.show', $existing)->with('duplicate', true);
            }
            throw $exception;
        }

        $request->session()->forget('open_library_preview');

        return redirect()->route('manager.books.show', $book)->with('registered', true);
    }

    public function show(Book $book)
    {
        return view('manager.books.show', ['book' => $book->load('copies')]);
    }

    private function duplicate(?string $isbn, ?string $edition): ?Book
    {
        if (! $isbn && ! $edition) {
            return null;
        }

        return Book::query()->where(function ($query) use ($isbn, $edition) {
            if ($isbn) {
                $query->where('isbn', $isbn);
            }
            if ($edition) {
                $query->orWhere('open_library_edition', $edition);
            }
        })->first();
    }
}
