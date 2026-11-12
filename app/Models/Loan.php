<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = [
        'student_id', 'copy_id', 'borrowed_at', 'due_at', 'returned_at', 'cancelled_at',
    ];

    protected $hidden = ['active_copy_id'];

    public function scopeOverdue(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->whereNull('returned_at')->whereNull('cancelled_at')->where('due_at', '<', $at ?? now());
    }

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Copy::class);
    }
}
