<?php

namespace App\Services;

use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Enums\CopyStatus;
use App\Enums\UserRole;
use App\Exceptions\CirculationException;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\ScanOperation;
use App\Support\AcademicEmail;
use Illuminate\Support\Facades\DB;

class CirculationService
{
    public function __construct(private readonly UserDirectory $directory) {}

    public function identify(string $qrCode): array
    {
        $resolved = $this->resolve($qrCode);

        return $resolved['type'] === 'student'
            ? ['type' => 'student', 'qr_code' => $qrCode, 'label' => $resolved['student']->name]
            : ['type' => 'copy', 'qr_code' => $qrCode, 'label' => $resolved['copy']->book->title];
    }

    public function scan(int $managerId, string $requestId, array $qrCodes): array
    {
        sort($qrCodes, SORT_STRING);

        return $this->once($managerId, $requestId, ['scan', $qrCodes], function () use ($qrCodes) {
            $resolved = array_map(fn (string $qr) => $this->resolve($qr), $qrCodes);
            if ($resolved[0]['type'] === $resolved[1]['type']) {
                throw new CirculationException('Scannez un QR d’exemplaire et un QR de carte étudiante.', 'invalid_pair');
            }

            $student = ($resolved[0]['type'] === 'student' ? $resolved[0] : $resolved[1])['student'];
            $scannedCopy = ($resolved[0]['type'] === 'copy' ? $resolved[0] : $resolved[1])['copy'];

            $this->lockStudent($student->id);
            $copy = Copy::whereKey($scannedCopy->id)->lockForUpdate()->firstOrFail();
            if ($copy->qr_code !== $scannedCopy->qr_code) {
                throw new CirculationException('L’identifiant de cet exemplaire a changé. Recommencez les scans.', 'copy_changed', 409);
            }

            $active = Loan::where('copy_id', $copy->id)->whereNull('returned_at')->whereNull('cancelled_at')
                ->lockForUpdate()->first();

            if (($active === null) !== ($copy->status === CopyStatus::Available)) {
                throw $this->inconsistentCopy();
            }

            if ($active !== null) {
                if ($active->student_id !== $student->id) {
                    throw new CirculationException('Cet exemplaire est actuellement emprunté par un autre étudiant. Le retour est refusé.', 'another_borrower');
                }

                $active->update(['returned_at' => now()->startOfSecond()]);
                $copy->update(['status' => CopyStatus::Available]);

                return $this->result('returned', 'Retour enregistré. L’exemplaire est disponible.', $active, $copy, $student);
            }

            // A locking read is required here: MySQL REPEATABLE READ may already
            // have a snapshot from QR resolution before waiting for the student lock.
            $activeCount = Loan::where('student_id', $student->id)
                ->whereNull('returned_at')->whereNull('cancelled_at')->lockForUpdate()->get(['id'])->count();
            if ($activeCount >= config('library.max_active_loans')) {
                throw new CirculationException('Emprunt refusé : cet étudiant a déjà '.config('library.max_active_loans').' exemplaires empruntés.', 'loan_limit');
            }

            $borrowedAt = now()->startOfSecond();
            $loan = Loan::create([
                'student_id' => $student->id,
                'copy_id' => $copy->id,
                'borrowed_at' => $borrowedAt,
                'due_at' => $borrowedAt->copy()->addDays(config('library.loan_duration_days')),
            ]);
            $copy->update(['status' => CopyStatus::Borrowed]);

            return $this->result('borrowed', 'Emprunt enregistré.', $loan, $copy, $student);
        });
    }

    public function cancel(int $managerId, string $requestId, int $loanId): array
    {
        return $this->once($managerId, $requestId, ['cancel', $loanId], function () use ($loanId) {
            $initial = Loan::find($loanId);
            if ($initial === null) {
                throw new CirculationException('Cet emprunt est introuvable.', 'loan_not_found', 404);
            }

            $this->lockStudent($initial->student_id);
            $copy = Copy::whereKey($initial->copy_id)->lockForUpdate()->firstOrFail();
            $loan = Loan::whereKey($loanId)->lockForUpdate()->firstOrFail();
            if ($loan->student_id !== $initial->student_id || $loan->copy_id !== $initial->copy_id) {
                throw $this->inconsistentCopy();
            }
            if ($loan->returned_at !== null || $loan->cancelled_at !== null) {
                throw new CirculationException('Cet emprunt est déjà retourné ou annulé. Il ne peut plus être annulé.', 'loan_closed', 409);
            }
            if ($copy->status !== CopyStatus::Borrowed) {
                throw $this->inconsistentCopy();
            }

            $loan->update(['cancelled_at' => now()->startOfSecond()]);
            $copy->update(['status' => CopyStatus::Available]);

            return $this->result('cancelled', 'Emprunt annulé. L’exemplaire est disponible.', $loan, $copy, $this->directory->findById($loan->student_id));
        });
    }

    private function resolve(string $qrCode): array
    {
        $student = $this->directory->findStudentByQrCode($qrCode);
        $copy = Copy::with('book')->where('qr_code', $qrCode)->first();
        $student = $student?->studentQrCode === $qrCode ? $student : null;
        $copy = $copy?->qr_code === $qrCode ? $copy : null;

        if ($student !== null && $copy !== null) {
            throw new CirculationException('Ce QR code correspond à une carte et à un exemplaire. Faites corriger les identifiants dans la base.', 'ambiguous_qr');
        }
        if ($student !== null) {
            if ($student->role !== UserRole::Student || ! AcademicEmail::isValid($student->email)) {
                throw new CirculationException('Cette carte ne correspond pas à un étudiant académique autorisé.', 'invalid_student');
            }

            return ['type' => 'student', 'student' => $student];
        }
        if ($copy !== null) {
            return ['type' => 'copy', 'copy' => $copy];
        }

        throw new CirculationException('QR code inconnu : aucune carte étudiante ni aucun exemplaire correspondant.', 'unknown_qr');
    }

    private function lockStudent(int $studentId): void
    {
        // Only lock the local foreign-key anchor; identity fields come from UserDirectory.
        if (DB::table('users')->where('id', $studentId)->lockForUpdate()->first(['id']) === null) {
            throw new CirculationException('L’étudiant n’a pas d’identifiant local correspondant. Vérifiez l’alimentation des données.', 'missing_local_student');
        }
    }

    private function once(int $managerId, string $requestId, array $input, callable $action): array
    {
        $fingerprint = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($managerId, $requestId, $fingerprint, $action) {
            ScanOperation::query()->createOrFirst(['id' => $requestId], [
                'manager_id' => $managerId, 'fingerprint' => $fingerprint,
            ]);
            $operation = ScanOperation::whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($operation->manager_id !== $managerId || ! hash_equals($operation->fingerprint, $fingerprint)) {
                $error = new CirculationException('Cette demande a déjà été utilisée pour une autre opération.', 'request_conflict', 409);

                return ['status' => $error->status, 'body' => $error->body()];
            }
            if ($operation->response !== null) {
                return ['status' => $operation->http_status, 'body' => $operation->response];
            }

            try {
                $body = $action();
                $status = 200;
            } catch (CirculationException $error) {
                $body = $error->body();
                $status = $error->status;
            }

            $operation->update(['http_status' => $status, 'response' => $body]);

            return ['status' => $status, 'body' => $body];
        }, 5);
    }

    private function result(string $operation, string $message, Loan $loan, Copy $copy, ?DirectoryUser $student): array
    {
        return [
            'operation' => $operation,
            'message' => $message,
            'loan_id' => $loan->id,
            'student' => ['id' => $loan->student_id, 'name' => $student?->name ?? 'Étudiant #'.$loan->student_id],
            'copy' => ['id' => $copy->id, 'qr_code' => $copy->qr_code, 'title' => $copy->book->title],
            'due_at' => $loan->due_at->toIso8601String(),
        ];
    }

    private function inconsistentCopy(): CirculationException
    {
        return new CirculationException('Le statut de cet exemplaire et son emprunt ne correspondent pas. Faites vérifier les données avant de recommencer.', 'inconsistent_copy', 409);
    }
}
