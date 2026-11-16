<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ], true)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas são inválidas.'],
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->current_company_id) {
            $firstCompany = $user->companies()->first();

            if ($firstCompany) {
                $user->update([
                    'current_company_id' => $firstCompany->id,
                ]);
            }
        }

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'user' => $user->load('currentCompany', 'companies'),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('currentCompany', 'companies'),
            'company_id' => app()->bound('company.id') ? app('company.id') : null,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }
}
