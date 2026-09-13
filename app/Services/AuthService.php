<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new user
     */
    public function register(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
        ]);
    }

    /**
     * Authenticate user and generate bearer token
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kombinasi email dan kata sandi tidak cocok.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('businesses');

        $activeBusiness = $user->businesses->first();

        return [
            'user' => $user,
            'token' => $token,
            'active_business' => $activeBusiness,
        ];
    }

    /**
     * Logout current user (revoke current token)
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Get profile data for authenticated user
     */
    public function getProfile(User $user): array
    {
        $user->load('businesses');

        return [
            'user' => $user,
            'businesses' => $user->businesses,
        ];
    }
}
