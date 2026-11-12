<?php

namespace App\Auth;

use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Support\AcademicEmail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

final class DirectoryUserProvider implements UserProvider
{
    public function __construct(private readonly UserDirectory $directory) {}

    public function retrieveById($identifier): ?DirectoryUser
    {
        $id = filter_var($identifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $user = $id === false ? null : $this->directory->findById($id);

        if ($user === null) {
            return null;
        }

        $isLocalDemo = app()->environment('local')
            && in_array(request()->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && in_array(request()->getHost(), ['localhost', '127.0.0.1'], true)
            && (($user->email === 'gerant@demo.test' && $user->role === \App\Enums\UserRole::Manager)
                || (in_array($user->email, ['salma@demo.test', 'youssef@demo.test'], true)
            && $user->role === \App\Enums\UserRole::Student));

        return AcademicEmail::isValid($user->email) || $isLocalDemo ? $user : null;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?DirectoryUser
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void {}

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?DirectoryUser
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void {}
}
