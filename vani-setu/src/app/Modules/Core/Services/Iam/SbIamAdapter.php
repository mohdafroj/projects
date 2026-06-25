<?php

namespace App\Modules\Core\Services\Iam;

use App\Modules\Core\Models\User;

class SbIamAdapter implements IamAdapter
{
    private readonly string $url;

    public function __construct(
        ?string $url = null,
    ) {
        $this->url = $url ?? config('services.sb_iam.url');
    }

    public function authenticate(string $employeeId, string $credential): ?User
    {
        throw new NotImplementedException('TODO: authenticate against sb-iam at '.$this->url);
    }

    public function refresh(User $user): bool
    {
        throw new NotImplementedException('TODO: refresh sb-iam session for user '.$user->getKey());
    }

    public function logout(User $user): bool
    {
        throw new NotImplementedException('TODO: logout sb-iam session for user '.$user->getKey());
    }
}
