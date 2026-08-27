<?php

declare(strict_types=1);

namespace HR\Identity\Presentation\Http\Controller;
 
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use HR\Identity\Presentation\Http\Request\ChangePasswordRequest;
use HR\Identity\Presentation\Http\Request\LoginRequest;
use HR\Identity\Presentation\Http\Resource\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = strtolower($request->string('email')->value()).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in '
                    .RateLimiter::availableIn($throttleKey).' seconds.',
            ])->status(429);
        }

        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            RateLimiter::hit($throttleKey, decaySeconds: 60);

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact HR.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken(
            $request->string('device_name')->value() ?: 'portal',
            expiresAt: now()->addHours(12),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new AuthenticatedUserResource($user->loadMissing('employee')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user()->loadMissing('employee'));
    }
    
    /**
     * Let a signed-in user change their own password. The current password must
     * be given and must match, so a stolen session token alone can't rotate the
     * password. All other sessions are signed out afterwards, so a leaked
     * temporary password stops working the moment the real one is set.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
 
        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }
 
        // The 'hashed' cast on the model hashes this on assignment.
        $user->password = $request->validated('new_password');
        $user->save();
 
        // Revoke every token except the one making this request, so other
        // devices are logged out but the user isn't kicked out mid-change.
        $current = $user->currentAccessToken();
        $user->tokens()->where('id', '!=', $current?->id)->delete();
 
        return response()->json(['message' => 'Password changed.']);
    }
}
