<?php

namespace App\Services;

use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Data\OverdueLoanData;
use App\Data\OverdueStudentData;
use App\Enums\OverdueLevel;
use App\Enums\UserRole;
use App\Models\Loan;
use App\Support\AcademicEmail;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OverdueReport
{
    public function __construct(
        private readonly UserDirectory $directory,
        private readonly OverduePolicy $policy,
    ) {}

    public function dashboard(): array
    {
        $at = now()->toImmutable();
        $loans = Loan::overdue($at)->with('copy.book')->orderBy('due_at')->get();
        $users = $loans->isEmpty() ? [] : $this->directory->findByIds($loans->pluck('student_id')->unique()->values()->all());
        $students = [];

        foreach ($loans->groupBy('student_id') as $studentId => $studentLoans) {
            $user = $users[$studentId] ?? null;
            if ($this->isEligible($user)) {
                $students[] = $this->toData($user, $studentLoans, $at);
            }
        }

        usort($students, fn (OverdueStudentData $a, OverdueStudentData $b) =>
            $b->maxDaysLate <=> $a->maxDaysLate ?: $a->student->id <=> $b->student->id);

        $levels = OverdueLevel::cases();
        $studentCounts = array_fill_keys(array_column($levels, 'value'), 0);
        $copyCounts = $studentCounts;

        foreach ($students as $student) {
            $studentCounts[$student->level->value]++;
            foreach ($student->loans as $loan) {
                $copyCounts[$loan->level->value]++;
            }
        }

        return [
            'students' => $students,
            'statistics' => [
                'students' => count($students),
                'copies' => array_sum($copyCounts),
                'maxDaysLate' => $students[0]->maxDaysLate ?? 0,
            ],
            'charts' => [
                'labels' => array_map(fn (OverdueLevel $level) => $level->label(), $levels),
                'colors' => array_map(fn (OverdueLevel $level) => $level->color(), $levels),
                'students' => array_values($studentCounts),
                'copies' => array_values($copyCounts),
            ],
            'generatedAt' => $at,
        ];
    }

    public function forStudent(int $studentId): ?OverdueStudentData
    {
        $student = $this->directory->findById($studentId);
        if (! $this->isEligible($student)) {
            return null;
        }

        $at = now()->toImmutable();
        $loans = Loan::overdue($at)->where('student_id', $studentId)
            ->with('copy.book')->orderBy('due_at')->get();

        return $loans->isEmpty() ? null : $this->toData($student, $loans, $at);
    }

    private function isEligible(?DirectoryUser $user): bool
    {
        return $user !== null && $user->role === UserRole::Student && AcademicEmail::isValid($user->email);
    }

    private function toData(DirectoryUser $student, Collection $loans, CarbonInterface $at): OverdueStudentData
    {
        $items = $loans->map(function (Loan $loan) use ($at) {
            $days = $this->policy->daysLate($loan->due_at, $at);

            return new OverdueLoanData(
                id: $loan->id,
                copyId: $loan->copy_id,
                copyQrCode: $loan->copy->qr_code,
                title: $loan->copy->book->title,
                author: $loan->copy->book->author,
                borrowedAt: $loan->borrowed_at,
                dueAt: $loan->due_at,
                daysLate: $days,
                level: $this->policy->level($days),
            );
        })->all();
        $maxDays = max(array_map(fn (OverdueLoanData $loan) => $loan->daysLate, $items));

        return new OverdueStudentData($student, $items, $maxDays, $this->policy->level($maxDays));
    }
}
