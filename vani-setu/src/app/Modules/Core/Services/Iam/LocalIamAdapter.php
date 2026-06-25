<?php

namespace App\Modules\Core\Services\Iam;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Auth;

class LocalIamAdapter implements IamAdapter
{
    public function authenticate(string $employeeId, string $credential): ?User
    {
        if (! Auth::attempt([
            'employee_id' => $employeeId,
            'password' => $credential,
            'is_active' => true,
        ])) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('local-iam');

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $user->setAttribute('plain_text_token', $token->plainTextToken);

        return $user;
    }

    public function refresh(User $user): bool
    {
        return $user->is_active;
    }

    public function logout(User $user): bool
    {
        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        Auth::guard('web')->logout();

        return true;
    }
}
