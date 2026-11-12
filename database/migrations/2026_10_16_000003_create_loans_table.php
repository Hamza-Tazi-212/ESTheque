<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('copy_id')->constrained()->restrictOnDelete();
            $table->dateTime('borrowed_at');
            $table->dateTime('due_at');
            $table->dateTime('returned_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            // A nullable unique key permits history, but only one active loan per copy.
            $table->unsignedBigInteger('active_copy_id')->nullable()->storedAs(
                'CASE WHEN returned_at IS NULL AND cancelled_at IS NULL THEN copy_id ELSE NULL END'
            );
            $table->unique('active_copy_id');
            $table->index(['student_id', 'returned_at', 'cancelled_at'], 'loans_student_active_index');
            $table->index(['returned_at', 'cancelled_at', 'due_at'], 'loans_overdue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
