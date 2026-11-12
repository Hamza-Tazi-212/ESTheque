<?php

namespace Tests\Feature;

use App\Contracts\UserDirectory;
use App\Data\DirectoryUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_returns_a_storage_independent_identity_for_both_roles(): void
    {
        $student = User::factory()->create(['email' => 'student@example.test']);
        $manager = User::factory()->manager()->create();
        $directory = app(UserDirectory::class);

        $identity = $directory->findByEmail('  STUDENT@EXAMPLE.TEST  ');

        $this->assertInstanceOf(DirectoryUser::class, $identity);
        $this->assertSame($student->id, $identity->id);
        $this->assertSame(UserRole::Student, $identity->role);
        $this->assertSame($student->student_qr_code, $identity->studentQrCode);
        $this->assertSame($student->id, $directory->findById($student->id)->id);
        $this->assertSame(UserRole::Manager, $directory->findByEmail($manager->email)->role);
    }

    public function test_student_card_lookup_excludes_managers(): void
    {
        $student = User::factory()->create();
        User::factory()->manager()->create(['student_qr_code' => 'manager-card']);
        $directory = app(UserDirectory::class);

        $this->assertSame($student->id, $directory->findStudentByQrCode($student->student_qr_code)->id);
        $this->assertNull($directory->findStudentByQrCode('manager-card'));
    }

    public function test_unknown_users_are_not_created(): void
    {
        $directory = app(UserDirectory::class);

        $this->assertNull($directory->findById(999));
        $this->assertNull($directory->findByEmail('absent@example.test'));
        $this->assertNull($directory->findStudentByQrCode('student:absent'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_bulk_lookup_returns_identities_by_stable_identifier(): void
    {
        $student = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $directory = app(UserDirectory::class);

        $users = $directory->findByIds([$student->id, $manager->id, 999]);

        $this->assertCount(2, $users);
        $this->assertInstanceOf(DirectoryUser::class, $users[$student->id]);
        $this->assertSame(UserRole::Manager, $users[$manager->id]->role);
        $this->assertSame([], $directory->findByIds([]));
    }
}
