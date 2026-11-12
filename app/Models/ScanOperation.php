<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanOperation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'manager_id', 'fingerprint', 'http_status', 'response'];

    protected function casts(): array
    {
        return ['http_status' => 'integer', 'response' => 'array'];
    }
}
