<?php

namespace Tests\Feature;

use App\Auth\DirectoryUserProvider;
use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Enums\UserRole;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['services.google' => [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'redirect' => 'http://localhost/auth/google/callback',
        ]]);
    }

    public function test_google_redirect_contains_state_and_only_identity_scopes(): void
    {
        $response = $this->get(route('auth.google.redirect'));
        $response->assertRedirect();
        $url = parse_url($response->headers->get('Location'));
        parse_str($url['query'], $query);

        $this->assertSame('accounts.google.com', $url['host']);
        $this->assertNotEmpty($query['state']);
        $response->assertSessionHas('state', $query['state']);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertArrayNotHasKey('prompt', $query);
        $this->assertSame(config('services.google.redirect'), $query['redirect_uri']);
    }

    #[DataProvider('roles')]
    public function test_registered_google_user_authenticates_and_reaches_the_correct_interface(string $role, string $route): void
    {
        $user = User::factory()->create([
            'email' => 'registered@usmba.ac.ma',
            'name' => 'Nom conservé en base',
            'role' => $role,
            'student_qr_code' => $role === 'student' ? 'student:known' : null,
        ]);
        $this->mockGoogle(['email' => 'REGISTERED@USMBA.AC.MA', 'email_verified' => true, 'role' => 'manager']);
        $this->withSession(['state' => 'valid-state', 'url.intended' => route('manager.index')]);
        $previousSessionId = session()->getId();

        $response = $this->get(route('auth.google.callback', ['state' => 'valid-state', 'code' => 'test-code']));

        $response->assertRedirect(route('home'));
        $response->assertSessionMissing('state');
        $response->assertSessionMissing('url.intended');
        $response->assertSessionHas(Auth::guard()->getName(), $user->id);
        $this->assertAuthenticatedAs(app(UserDirectory::class)->findById($user->id));
        $this->assertInstanceOf(DirectoryUser::class, Auth::user());
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertSame('Nom conservé en base', $user->fresh()->name);
        $this->assertDatabaseCount('users', 1);
        $this->assertStringNotContainsString('test-access-token', serialize(session()->all()));

        Auth::forgetGuards();
        $this->get(route('home'))->assertRedirect(route($route));
        $this->get(route($route))->assertOk();
    }

    public static function roles(): array
    {
        return [
            'student' => ['student', 'student.index'],
            'manager' => ['manager', 'manager.index'],
        ];
    }

    #[DataProvider('roles')]
    public function test_both_interfaces_redirect_guests_to_google(string $role, string $route): void
    {
        $this->get(route($route))->assertRedirect(route('auth.google.redirect'));
    }

    public function test_existing_session_does_not_start_a_new_google_login(): void
    {
        $student = User::factory()->create();
        $this->withSession([Auth::guard()->getName() => $student->id]);

        $this->get(route('auth.google.redirect'))->assertRedirect(route('home'));
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));
    }

    public function test_academic_name_is_escaped_and_interface_is_not_cached(): void
    {
        $student = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->withSession([Auth::guard()->getName() => $student->id]);

        $response = $this->get(route('student.index'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_unknown_verified_google_email_is_refused_without_registration(): void
    {
        $this->mockGoogle(['email' => 'unknown@usmba.ac.ma', 'email_verified' => true]);

        $this->withSession(['state' => 'valid-state'])
            ->get(route('auth.google.callback', ['state' => 'valid-state', 'code' => 'test-code']))
            ->assertForbidden()->assertSee('n’est pas enregistré');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('invalidEmails')]
    public function test_missing_invalid_or_unverified_google_email_is_refused(?string $email, mixed $verified): void
    {
        User::factory()->create(['email' => 'known@usmba.ac.ma']);
        $this->mockGoogle(['email' => $email, 'email_verified' => $verified]);

        $this->withSession(['state' => 'valid-state'])
            ->get(route('auth.google.callback', ['state' => 'valid-state', 'code' => 'test-code']))
            ->assertForbidden();

        $this->assertGuest();
    }

    public static function invalidEmails(): array
    {
        return [
            'missing' => [null, true],
            'invalid' => ['invalid-email', true],
            'unverified' => ['known@usmba.ac.ma', false],
            'no verification claim' => ['known@usmba.ac.ma', null],
        ];
    }

    #[DataProvider('externalEmails')]
    public function test_another_domain_is_refused_even_when_present_in_the_directory(string $email): void
    {
        User::factory()->create(['email' => $email]);
        $this->mockGoogle(['email' => $email, 'email_verified' => true]);

        $this->withSession(['state' => 'valid-state'])
            ->get(route('auth.google.callback', ['state' => 'valid-state', 'code' => 'test-code']))
            ->assertForbidden()->assertSee('@usmba.ac.ma');

        $this->assertGuest();
    }

    public static function externalEmails(): array
    {
        return [
            'personal account' => ['hamza.tazi5@gmail.com'],
            'fake suffix' => ['hamza.tazi5@usmba.ac.ma.example.test'],
            'subdomain' => ['hamza.tazi5@students.usmba.ac.ma'],
            'similar domain' => ['hamza.tazi5@fakeusmba.ac.ma'],
        ];
    }

    public function test_a_session_loses_access_if_its_email_is_changed_to_an_external_domain(): void
    {
        $student = User::factory()->create(['email' => 'hamza.tazi5@usmba.ac.ma']);
        $this->withSession([Auth::guard()->getName() => $student->id]);
        $this->get(route('student.index'))->assertOk();

        $student->update(['email' => 'hamza.tazi5@gmail.com']);
        Auth::forgetGuards();

        $this->get(route('student.index'))->assertRedirect(route('auth.google.redirect'));
    }

    #[DataProvider('invalidStates')]
    public function test_invalid_oauth_state_is_rejected_without_contacting_google(?string $stored, ?string $received): void
    {
        $mock = new MockHandler([]);
        $this->useGoogleHttpMock($mock);
        $this->withSession($stored === null ? [] : ['state' => $stored]);

        $this->get(route('auth.google.callback', ['state' => $received, 'code' => 'test-code']))
            ->assertStatus(400)->assertSee('expirée ou invalide');

        $this->assertGuest();
    }

    public static function invalidStates(): array
    {
        return [
            'different' => ['expected', 'forged'],
            'missing session' => [null, 'unsolicited'],
            'missing callback state' => ['expected', null],
        ];
    }

    public function test_google_cancellation_is_reported_without_a_redirect_loop(): void
    {
        $this->withSession(['state' => 'cancelled'])
            ->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertForbidden()->assertSee('refusée ou annulée')->assertSessionMissing('state');

        $this->assertGuest();
    }

    public function test_google_network_failure_has_a_clear_message(): void
    {
        $mock = new MockHandler([new ConnectException('Offline', new Request('POST', 'https://www.googleapis.com'))]);
        $this->useGoogleHttpMock($mock);

        $this->withSession(['state' => 'valid-state'])
            ->get(route('auth.google.callback', ['state' => 'valid-state', 'code' => 'test-code']))
            ->assertStatus(503)->assertSee('momentanément indisponible');

        $this->assertGuest();
    }

    public function test_missing_configuration_is_reported_without_showing_secrets(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('auth.google.redirect'))->assertStatus(503)->assertSee('pas encore configurée');
    }

    public function test_student_cannot_access_manager_routes_and_manager_cannot_access_student_routes(): void
    {
        $student = User::factory()->create();
        $manager = User::factory()->manager()->create();

        $this->withSession([Auth::guard()->getName() => $student->id]);
        $this->get(route('manager.index'))->assertForbidden();

        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => $manager->id]);
        $this->get(route('student.index'))->assertForbidden();
    }

    public function test_removed_user_loses_session_access(): void
    {
        $student = User::factory()->create();
        $this->withSession([Auth::guard()->getName() => $student->id]);
        $this->get(route('student.index'))->assertOk();
        $student->delete();
        Auth::forgetGuards();

        $this->get(route('student.index'))->assertRedirect(route('auth.google.redirect'));
    }

    public function test_changed_role_is_reloaded_from_the_directory_on_the_next_request(): void
    {
        $manager = User::factory()->manager()->create();
        $this->withSession([Auth::guard()->getName() => $manager->id]);
        $this->get(route('manager.index'))->assertOk();
        $manager->update(['role' => UserRole::Student]);
        Auth::forgetGuards();

        $this->get(route('manager.index'))->assertForbidden();
        $this->get(route('home'))->assertRedirect(route('student.index'));
    }

    public function test_sessions_can_use_an_alternative_directory_without_a_local_user_query(): void
    {
        $identity = new DirectoryUser(867, 'Annuaire externe', 'api@usmba.ac.ma', UserRole::Student, 'student:api');
        $directory = Mockery::mock(UserDirectory::class);
        $directory->shouldReceive('findById')->once()->with(867)->andReturn($identity);
        $this->app->instance(UserDirectory::class, $directory);
        Auth::forgetGuards();
        $this->withSession([Auth::guard()->getName() => 867]);

        $this->get(route('student.index'))->assertOk()->assertSee('Annuaire externe');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_and_persistent_token_authentication_are_disabled(): void
    {
        $identity = new DirectoryUser(1, 'Test', 'test@example.test', UserRole::Student, 'student:test');
        $provider = app(DirectoryUserProvider::class);

        $this->assertNull($provider->retrieveByCredentials(['email' => $identity->email, 'password' => 'anything']));
        $this->assertFalse($provider->validateCredentials($identity, ['password' => 'anything']));
        $this->assertNull($provider->retrieveByToken(1, 'anything'));
        $this->assertNull($provider->retrieveById('invalid'));
        $this->get('/login')->assertNotFound();
        $this->get('/register')->assertNotFound();
    }

    private function mockGoogle(array $claims): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'test-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'sub' => 'google-test-id', 'name' => 'Nom Google à ne pas importer', ...$claims,
            ])),
        ]);

        $this->useGoogleHttpMock($mock);
    }

    private function useGoogleHttpMock(MockHandler $mock): void
    {
        $provider = Socialite::driver('google');
        $provider->setHttpClient(new Client(['handler' => HandlerStack::create($mock)]));

        // The test creates the provider before the HTTP request exists.
        $this->app->rebinding('request', fn ($app, $request) => $provider->setRequest($request));
    }
}
