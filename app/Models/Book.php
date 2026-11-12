<?php

namespace App\Models;

use App\Enums\CopyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = ['title', 'author', 'summary', 'table_of_contents', 'cover_image',
        'isbn', 'subtitle', 'publisher', 'publication_date', 'edition_statement', 'language',
        'subjects', 'open_library_edition', 'open_library_work', 'open_library_cover'];

    public function coverUrl(string $size = 'L'): ?string
    {
        if ((int) $this->open_library_cover > 0) {
            $size = $size === 'M' ? 'M' : 'L';
            return 'https://covers.openlibrary.org/b/id/'.(int) $this->open_library_cover.'-'.$size.'.jpg?default=false';
        }

        return $this->cover_image_url;
    }

    protected function openLibrarySourceUrl(): Attribute
    {
        return Attribute::get(fn () => $this->open_library_edition
            ? 'https://openlibrary.org/books/'.$this->open_library_edition : null);
    }

    public function scopeWithAvailability(Builder $query): void
    {
        $query->withCount([
            'copies',
            'copies as available_copies_count' => fn (Builder $copies) => $copies->where('status', CopyStatus::Available),
        ]);
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = trim((string) $this->cover_image);

            if (str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
                return $path;
            }

            return filter_var($path, FILTER_VALIDATE_URL) && parse_url($path, PHP_URL_SCHEME) === 'https'
                ? $path
                : null;
        });
    }

    public function copies(): HasMany
    {
        return $this->hasMany(Copy::class);
    }
}
