<?php

namespace App\Repositories;

use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

final class EloquentUserDirectory implements UserDirectory
{
    public function findById(int $id): ?DirectoryUser
    {
        return $this->toData(User::find($id));
    }

    public function findByIds(array $ids): array
    {
        return User::whereIn('id', $ids)->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $this->toData($user)])->all();
    }

    public function findByEmail(string $email): ?DirectoryUser
    {
        return $this->toData(User::where('email', Str::lower(trim($email)))->first());
    }

    public function findStudentByQrCode(string $qrCode): ?DirectoryUser
    {
        return $this->toData(User::where('role', UserRole::Student)
            ->where('student_qr_code', $qrCode)->first());
    }

    private function toData(?User $user): ?DirectoryUser
    {
        return $user === null ? null : new DirectoryUser(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
            studentQrCode: $user->student_qr_code,
        );
    }
}
