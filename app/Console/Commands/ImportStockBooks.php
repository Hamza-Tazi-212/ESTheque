<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\OpenLibrary;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class ImportStockBooks extends Command
{
    protected $signature = 'library:import-stock-books {--file= : Fichier texte avec un ISBN du stock par ligne}';

    protected $description = 'Ajouter les notices Open Library correspondant aux ISBN du stock physique';

    public function handle(OpenLibrary $library): int
    {
        $path = $this->option('file') ?: storage_path('app/private/stock-isbn.txt');
        if (! is_file($path) && ! $this->option('file')) {
            $this->info('Aucune liste ISBN du stock configurée.');

            return self::SUCCESS;
        }

        if (! is_file($path) || ! is_readable($path) || filesize($path) > 8192) {
            $this->error('Liste ISBN inaccessible ou trop volumineuse.');

            return self::FAILURE;
        }

        $isbns = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim(Str::replaceStart("\xEF\xBB\xBF", '', $line));
            if ($line === '') {
                continue;
            }
            $isbn = OpenLibrary::isbn($line);
            if ($isbn === null) {
                $this->error('La liste contient un ISBN invalide. Corrigez-la avant de relancer.');

                return self::FAILURE;
            }
            $isbns[$isbn] = strtoupper(preg_replace('/[\s-]+/', '', $line));
        }

        $pending = array_diff_key($isbns, Book::whereIn('isbn', array_keys($isbns))->pluck('isbn')->flip()->all());
        if (count($pending) > 20) {
            $this->error('Traitez au maximum 20 nouveaux ISBN à la fois.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($pending as $isbn => $lookup) {
            try {
                $metadata = $library->edition($lookup, (string) $isbn);
            } catch (RuntimeException $exception) {
                $this->error($isbn.' · '.$exception->getMessage());

                return self::FAILURE;
            }

            if ($metadata === null) {
                $this->warn($isbn.' · Édition introuvable.');
                $failed = true;

                continue;
            }

            $edition = $metadata['open_library_edition'];
            if (Book::where('open_library_edition', $edition)->exists()) {
                $this->line($isbn.' · Édition déjà enregistrée.');

                continue;
            }

            $data = Arr::only($metadata, [
                'title', 'subtitle', 'author', 'isbn', 'publisher', 'publication_date',
                'edition_statement', 'language', 'summary', 'subjects', 'open_library_edition',
                'open_library_work', 'open_library_cover',
            ]);
            foreach (['title', 'subtitle', 'author', 'publisher', 'publication_date', 'edition_statement', 'language'] as $field) {
                if (isset($data[$field]) && Str::length($data[$field]) > 255) {
                    $this->warn($isbn.' · Champ trop long : '.$field);
                    $failed = true;

                    continue 2;
                }
            }

            try {
                Book::create(array_replace([
                    'table_of_contents' => '',
                    'cover_image' => '',
                ], $data, ['author' => $data['author'] ?? '', 'summary' => $data['summary'] ?? '']));
            } catch (UniqueConstraintViolationException $exception) {
                if (! Book::where('isbn', $isbn)->orWhere('open_library_edition', $edition)->exists()) {
                    throw $exception;
                }

                continue;
            }

            $this->info($isbn.' · Notice ajoutée.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
