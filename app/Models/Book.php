<?php

namespace App\Models;

use App\Enums\CopyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = ['title', 'author', 'summary', 'table_of_contents', 'cover_image'];

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
            $path = trim($this->cover_image);

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
