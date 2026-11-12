<?php

namespace App\Http\Controllers\Manager;

use App\Contracts\UserDirectory;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\SendOverdueReminder;
use App\Models\Loan;
use App\Support\AcademicEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function store(Request $request, UserDirectory $directory): RedirectResponse
    {
        $input = $request->validate(['student_id' => 'sometimes|required|integer|min:1']);
        if (! in_array(config('mail.default'), ['smtp', 'ses', 'resend', 'postmark', 'mailgun'], true)
            && ! app()->environment('testing')) {
            return back()->withErrors(['reminder' => 'L’envoi des e-mails n’est pas encore configuré.']);
        }

        $students = Loan::overdue()->select('student_id')->distinct();
        if (isset($input['student_id'])) {
            $students->where('student_id', $input['student_id']);
        }

        $count = 0;
        $students->chunkById(200, function ($loans) use ($directory, &$count) {
            foreach ($directory->findByIds($loans->pluck('student_id')->all()) as $user) {
                if ($user->role === UserRole::Student && AcademicEmail::isValid($user->email)) {
                    SendOverdueReminder::dispatch($user->id)->onQueue('library-reminders');
                    $count++;
                }
            }
        }, 'student_id');

        if ($count === 0) {
            return back()->withErrors(['reminder' => 'Aucun étudiant en retard avec une adresse académique valide.']);
        }

        return back()->with('reminder_status', $count === 1
            ? 'Relance confiée à la file d’envoi.'
            : $count.' relances confiées à la file d’envoi.');
    }
}
