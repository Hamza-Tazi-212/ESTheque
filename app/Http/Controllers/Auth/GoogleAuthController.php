<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\UserDirectory;
use App\Http\Controllers\Controller;
use App\Support\AcademicEmail;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

final class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse|Response
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        if (! $this->isConfigured()) {
            return $this->unavailable();
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, UserDirectory $directory): RedirectResponse|Response
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        if ($request->has('error')) {
            $request->session()->forget('state');

            return $this->denied('La connexion Google a été refusée ou annulée. Utilisez votre compte académique pour accéder à la bibliothèque.');
        }

        if (! $this->isConfigured()) {
            return $this->unavailable();
        }

        try {
            // Keep Socialite's session state verification enabled.
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return response()->view('auth.failure', [
                'title' => 'Connexion expirée',
                'message' => 'La demande de connexion est expirée ou invalide. Recommencez la connexion Google.',
            ], 400);
        } catch (GuzzleException) {
            return $this->unavailable('Google est momentanément indisponible. Réessayez la connexion plus tard.');
        }

        $email = $googleUser->getEmail();
        $raw = $googleUser->getRaw();
        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $verified !== true) {
            return $this->denied('Google n’a pas confirmé une adresse email valide pour ce compte. Utilisez votre compte académique.');
        }

        $email = AcademicEmail::normalize($email);

        if (! AcademicEmail::isValid($email)) {
            return $this->denied('Accès refusé : utilisez votre adresse académique @'.config('library.academic_email_domain').'.');
        }

        $user = $directory->findByEmail($email);

        if ($user === null) {
            return $this->denied('Accès refusé : cet email académique n’est pas enregistré dans la bibliothèque de l’EST Fès.');
        }

        // Store only the local identifier; neither Google tokens nor profile changes are persisted.
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('home');
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function denied(string $message): Response
    {
        return response()->view('auth.failure', ['title' => 'Accès refusé', 'message' => $message], 403);
    }

    private function unavailable(string $message = 'La connexion Google n’est pas encore configurée. Contactez la bibliothèque.'): Response
    {
        return response()->view('auth.failure', ['title' => 'Connexion indisponible', 'message' => $message], 503);
    }
}
