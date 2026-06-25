<?php

namespace App\Modules\Core\Services\Iam;

use App\Modules\Core\Models\User;

interface IamAdapter
{
    public function authenticate(string $employeeId, string $credential): ?User;

    public function refresh(User $user): bool;

    public function logout(User $user): bool;
}
