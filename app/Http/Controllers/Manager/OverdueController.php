<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\User;
use App\Services\OverduePolicy;
use App\Services\OverdueReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OverdueController extends Controller
{
    public function index(Request $request, OverduePolicy $policy): View
    {
        $generatedAt = now()->toImmutable()->setTimezone(config('library.timezone'));
        $tomorrow = $generatedAt->startOfDay()->addDay();
        $dayAfterTomorrow = $tomorrow->addDay();
        $loans = Loan::query()->whereNull('returned_at')->whereNull('cancelled_at')->with('copy.book')->orderBy('due_at')->get(['due_at', 'borrowed_at', 'student_id', 'copy_id']);

        $overdue = $longest = $dueToday = $dueTomorrow = 0;

        $lateLoans = [];
        $todayLoans = [];
        $lateStudents = [];
        $reminderEmails = [];
        $students = User::query()->whereIn('id', $loans->pluck('student_id')->unique())->get()->keyBy('id');

        foreach ($loans as $loan) {
            if ($loan->due_at->lt($generatedAt)) {
                $overdue++;
                $days = $policy->daysLate($loan->due_at, $generatedAt);
                $longest = max($longest, $days);
                $student = $students->get($loan->student_id);
                $email = $student?->email;
                $email = is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
                    && str_ends_with(strtolower($email), '@'.config('library.academic_email_domain'))
                    ? $email : null;
                $lateStudents[$loan->student_id] = true;
                if ($email) {
                    $reminderEmails[$loan->student_id] = $email;
                }
                $lateLoans[] = [
                    'title' => $loan->copy->book->title,
                    'name' => $student?->name ?? 'Étudiant',
                    'due' => $loan->due_at->setTimezone($generatedAt->getTimezone())->format('d/m'),
                    'days' => $days,
                    'email' => $email,
                ];
            } elseif ($loan->due_at->lt($tomorrow)) {
                $dueToday++;
                $todayLoans[] = [
                    'title' => $loan->copy->book->title,
                    'name' => $students->get($loan->student_id)?->name ?? 'Étudiant',
                    'borrowed' => $loan->borrowed_at->setTimezone($generatedAt->getTimezone())->format('d/m'),
                ];
            } elseif ($loan->due_at->lt($dayAfterTomorrow)) {
                $dueTomorrow++;
            }
        }

        $filter = $request->query('prets');
        if (! in_array($filter, ['retard', 'rendus'], true)) {
            $filter = 'tous';
        }

        $onTimeReturns = collect();
        if ($filter !== 'retard') {
            $returns = Loan::query()->whereNull('cancelled_at')
                ->whereBetween('returned_at', [$generatedAt->subDays(7)->utc(), $generatedAt->utc()])
                ->whereColumn('returned_at', '<=', 'due_at')
                ->with('copy.book')->orderByDesc('returned_at')
                ->get(['student_id', 'copy_id', 'returned_at']);
            $returnStudents = User::query()->whereIn('id', $returns->pluck('student_id')->unique())
                ->pluck('name', 'id');
            $onTimeReturns = $returns->map(fn (Loan $loan) => [
                'title' => $loan->copy->book->title,
                'name' => $returnStudents->get($loan->student_id, 'Étudiant'),
                'returned' => $loan->returned_at->setTimezone($generatedAt->getTimezone())->format('d/m'),
            ]);
        }

        return view('manager.index', [
            'filter' => $filter,
            'onTimeReturns' => $onTimeReturns,
            'generatedAt' => $generatedAt,
            'overdue' => $overdue,
            'longest' => $longest,
            'dueToday' => $dueToday,
            'todayLoans' => $todayLoans,
            'dueTomorrow' => $dueTomorrow,
            'active' => $loans->count(),
            'lateLoans' => $lateLoans,
            'lateStudentCount' => count($lateStudents),
            'reminderEmails' => array_values($reminderEmails),
        ]);
    }

    public function show(int $student, OverdueReport $report): View
    {
        $data = $report->forStudent($student);
        abort_if($data === null, 404);

        return view('manager.student', ['report' => $data]);
    }
}
