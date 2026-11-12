<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Manager\OverdueController;
use App\Http\Controllers\Manager\ScanController;
use App\Http\Controllers\Student\CatalogueController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware('auth')->group(function () {

    Route::get('/', HomeController::class)->name('home');

    Route::middleware('role:student')->group(function () {
        Route::get('/etudiant', [CatalogueController::class, 'index'])->name('student.index');
        Route::get('/etudiant/ouvrages/{book}', [CatalogueController::class, 'show'])
            ->whereNumber('book')->name('student.books.show');
    });

    Route::middleware('role:manager')->group(function () {
        Route::get('/gerant/scan', [ScanController::class, 'index'])->name('manager.scan');
        Route::post('/gerant/scan/identifier', [ScanController::class, 'identify'])->name('manager.scan.identify');
        Route::post('/gerant/scan/enregistrer', [ScanController::class, 'store'])->name('manager.scan.store');
        Route::post('/gerant/emprunts/{loan}/annuler', [ScanController::class, 'cancel'])
            ->whereNumber('loan')->name('manager.loans.cancel');
        Route::get('/gerant', [OverdueController::class, 'index'])->name('manager.index');
        Route::get('/gerant/retards/etudiants/{student}', [OverdueController::class, 'show'])
            ->whereNumber('student')->name('manager.overdue.show');
    });
});

if (app()->environment('production')) {
    Route::match(['get', 'post'], '/demo/connexion', function (\Illuminate\Http\Request $request) {
        abort_unless($request->isSecure(), 404);

        if ($request->isMethod('post')) {
            $data = $request->validate(['email' => 'required|email', 'password' => 'required|string|max:100']);
            $email = strtolower($data['email']);
            abort_unless(($email === 'gerant@demo.test' && password_verify($data['password'], '$2y$10$q5SXclN9l5j6ULvkEWqQXe0TGvCnAtGsmnHUM/pXmnOsvsAF/nHF6')) || (in_array($email, ['salma@demo.test', 'youssef@demo.test'], true)
                && password_verify($data['password'], '$2y$10$XtehJzIevM6V572N84Xyq.ZvCntS8KJ1w6HzKKJ4VGUqPI./26LX6')), 403, 'Identifiants de démonstration incorrects.');

            $user = \App\Models\User::where('email', $email)->firstOrFail();
            abort_unless($user->role === ($email === 'gerant@demo.test' ? \App\Enums\UserRole::Manager : \App\Enums\UserRole::Student), 403);
            \Illuminate\Support\Facades\Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('home');
        }

        return response('<!doctype html><html lang="fr"><meta charset="utf-8"><title>Connexion de démonstration</title>'
            .'<h1>Connexion de démonstration</h1><form method="post">'.csrf_field()
            .'<p><label>Identifiant <input name="email" type="email" required></label></p>'
            .'<p><label>Mot de passe <input name="password" type="password" required></label></p>'
            .'<button>Se connecter</button></form></html>');
    })->middleware('throttle:15,1');
}

