<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->char('fingerprint', 64);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_operations');
    }
};
