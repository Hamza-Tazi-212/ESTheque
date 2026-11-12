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
        $query = Book::query()->select(['id', 'title', 'author', 'summary', 'open_library_cover'])->withAvailability();

        $filters = [
            'all' => 'Tous les ouvrages',
            'available' => 'Disponibles',
            'computing' => 'Informatique',
            'algorithms' => 'Algorithmique',
        ];
        $disciplines = config('catalogue-disciplines', []);
        $filters += array_map(fn ($discipline) => $discipline['label'], $disciplines);
        $filter = $request->query('filter');
        $filter = is_string($filter) && isset($filters[$filter]) ? $filter : 'all';

        $availableOnly = $filter === 'available' || $request->query('available') === '1';

        if ($availableOnly) {
            $query->whereHas('copies', fn (Builder $copies) => $copies->where('status', \App\Enums\CopyStatus::Available->value));
        }

        if (isset($disciplines[$filter])) {
            $query->whereIn('isbn', array_keys($disciplines[$filter]['books']));
        }

        if (in_array($filter, ['computing', 'algorithms'], true)) {
            $terms = $filter === 'algorithms'
                ? ['algorithm']
                : ['algorithm', 'programm', 'code', 'software', 'réseau', 'network', 'système', 'system', 'informatique', 'python', 'données', 'data', 'design', 'refactor'];
            $query->where(function (Builder $query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhereRaw('LOWER(title) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(summary) LIKE ?', ['%'.$term.'%']);
                }
            });
        }

        if ($search !== '') {
            // User-entered LIKE wildcards are searched literally.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereRaw("LOWER(title) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(author) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(summary) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            });
        }

        return view('student.index', [
            'books' => $query->orderBy('title')->orderBy('id')->paginate(10)
                ->appends([
                    'q' => $search !== '' ? $search : null,
                    'filter' => $filter === 'all' ? null : $filter,
                    'available' => $availableOnly && $filter !== 'available' ? '1' : null,
                ])
                ->fragment('catalogue'),
            'search' => $search,
            'filter' => $filter,
            'filters' => $filters,
            'availableOnly' => $availableOnly,
        ]);
    }

    public function show(CatalogueSearchRequest $request, Book $book): View
    {
        $book = Book::query()->withAvailability()->findOrFail($book->id);

        return view('student.show', ['book' => $book, 'search' => $request->searchTerm()]);
    }
}
