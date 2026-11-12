<?php

namespace App\Contracts;

use App\Data\DirectoryUser;

interface UserDirectory
{
    public function findById(int $id): ?DirectoryUser;

    /** @param list<int> $ids
     *  @return array<int, DirectoryUser>
     */
    public function findByIds(array $ids): array;

    public function findByEmail(string $email): ?DirectoryUser;

    public function findStudentByQrCode(string $qrCode): ?DirectoryUser;
}
