<?php

namespace Database\Seeders;

use App\Enums\CopyStatus;
use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Les données de démonstration sont réservées aux environnements local et testing.');
        }

        DB::transaction(function () {
            foreach (['users', 'books', 'copies', 'loans'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new LogicException('La base contient déjà des données métier. Utilisez une base de démonstration vide ; aucune donnée existante n’a été modifiée.');
                }
            }

            $studentData = [
                ['Hamza Tazi — test', 'hamza.tazi5@usmba.ac.ma'],
                ['Salma El Alami — démo', 'salma.elalami2@usmba.ac.ma'],
                ['Youssef Bennani — démo', 'youssef.bennani3@usmba.ac.ma'],
                ['Aya Idrissi — démo', 'aya.idrissi4@usmba.ac.ma'],
            ];

            $students = collect($studentData)->map(fn (array $data, int $index) => User::create([
                'name' => $data[0],
                'email' => $data[1],
                'role' => UserRole::Student,
                'student_qr_code' => sprintf('student:DEMO-%03d', $index + 1),
            ]));

            User::create([
                'name' => 'Nabil Amrani — gérant démo',
                'email' => 'nabil.amrani1@usmba.ac.ma',
                'role' => UserRole::Manager,
                'student_qr_code' => null,
            ]);

            $catalogue = [
                ['ALG', 'Algorithmique et structures de données', 'Notions de base sur les algorithmes, les listes et les arbres.', "1. Algorithmes\n2. Listes\n3. Arbres"],
                ['BDD', 'Bases de données relationnelles', 'Présentation du modèle relationnel et des requêtes SQL.', "1. Tables et relations\n2. SQL\n3. Transactions"],
                ['WEB', 'Programmation web', 'Découverte des échanges entre un navigateur et un serveur web.', "1. HTTP\n2. HTML et CSS\n3. Développement serveur"],
                ['RES', 'Réseaux informatiques', 'Introduction aux protocoles et à l’adressage réseau.', "1. Modèle réseau\n2. Adressage IP\n3. Protocoles"],
            ];

            $copies = [];

            foreach ($catalogue as [$code, $title, $summary, $contents]) {
                $book = Book::create([
                    'title' => $title.' (démo)',
                    'author' => 'Auteur fictif — démonstration',
                    'summary' => $summary.' Texte fictif pour les essais de l’application.',
                    'table_of_contents' => $contents,
                    'cover_image' => '/demo/cover.svg',
                ]);

                foreach (range(1, 3) as $number) {
                    $copies[$code][$number] = Copy::create([
                        'book_id' => $book->id,
                        'qr_code' => sprintf('copy:DEMO-%s-%02d', $code, $number),
                        'status' => CopyStatus::Available,
                    ]);
                }
            }

            $duration = config('library.loan_duration_days');
            $now = now();

            $this->createLoan($students[0], $copies['ALG'][1], $now->copy()->subDays($duration + 1));
            $this->createLoan($students[0], $copies['BDD'][1], $now->copy()->subDays($duration + (int) floor($duration / 3) + 1));
            $this->createLoan($students[0], $copies['WEB'][1], $now->copy()->subDays((int) floor($duration / 2)));
            $this->createLoan($students[1], $copies['RES'][1], $now->copy()->subDays($duration + (int) floor(2 * $duration / 3) + 1));
            $this->createLoan($students[2], $copies['BDD'][2], $now->copy()->subDays($duration + 5), [
                'returned_at' => $now->copy()->subDays(7),
            ]);
            $this->createLoan($students[2], $copies['WEB'][2], $now->copy()->subDays(3), [
                'cancelled_at' => $now->copy()->subDays(3)->addMinute(),
            ]);
        });
    }

    private function createLoan(User $student, Copy $copy, Carbon $borrowedAt, array $closed = []): void
    {
        Loan::create([
            'student_id' => $student->id,
            'copy_id' => $copy->id,
            'borrowed_at' => $borrowedAt,
            'due_at' => $borrowedAt->copy()->addDays(config('library.loan_duration_days')),
            ...$closed,
        ]);

        if ($closed === []) {
            $copy->update(['status' => CopyStatus::Borrowed]);
        }
    }
}
