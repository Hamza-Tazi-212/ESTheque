<?php

namespace App\Models;

use App\Enums\CopyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Copy extends Model
{
    protected $fillable = ['book_id', 'qr_code', 'status'];

    protected function casts(): array
    {
        return ['status' => CopyStatus::class];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
