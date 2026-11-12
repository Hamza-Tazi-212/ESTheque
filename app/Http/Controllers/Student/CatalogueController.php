<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogueSearchRequest;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function index(CatalogueSearchRequest $request): View
    {
        $search = $request->searchTerm();
        $query = Book::query()->select(['id', 'title', 'author', 'summary'])->withAvailability();

        if ($search !== '') {
            // User-entered LIKE wildcards are searched literally.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereRaw("LOWER(title) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(author) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            });
        }

        return view('student.index', [
            'books' => $query->orderBy('title')->orderBy('id')->get(),
            'search' => $search,
        ]);
    }

    public function show(CatalogueSearchRequest $request, Book $book): View
    {
        $book = Book::query()->withAvailability()->findOrFail($book->id);

        return view('student.show', ['book' => $book, 'search' => $request->searchTerm()]);
    }
}
