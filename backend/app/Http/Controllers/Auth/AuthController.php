<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $data = $request->validated();

        $authenticated = Auth::guard('web')->attempt([
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'is_active' => true,
        ]);

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกปิดใช้งาน',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        if (! ($user instanceof User)) {
            abort(401, 'Unauthenticated.');
        }

        $user->load('department');

        return new UserResource($user);
    }

   public function me(Request $request): UserResource
{
    $user = $request->user();

    if (! ($user instanceof User)) {
        abort(401, 'Unauthenticated.');
    }

    $user->load('department');

    return new UserResource($user);
}

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}