<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn', 13)->nullable()->unique();
            $table->string('open_library_edition', 32)->nullable()->unique();
            $table->string('open_library_work', 32)->nullable();
            $table->unsignedBigInteger('open_library_cover')->nullable();
            foreach (['subtitle', 'publisher', 'publication_date', 'edition_statement', 'language'] as $column) {
                $table->string($column)->nullable();
            }
            $table->text('subjects')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['isbn']);
            $table->dropUnique(['open_library_edition']);
            $table->dropColumn(['isbn', 'open_library_edition', 'open_library_work', 'open_library_cover',
                'subtitle', 'publisher', 'publication_date', 'edition_statement', 'language', 'subjects']);
        });
    }
};
