<?php

declare(strict_types=1);

namespace HR\Identity\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any signed-in user may change their own password; the route is behind
        // auth:sanctum and the handler only ever touches the current user.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'different:current_password',
                'confirmed', // expects new_password_confirmation
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.different' => 'The new password must be different from your current one.',
            'new_password.confirmed' => 'The confirmation does not match the new password.',
        ];
    }
}