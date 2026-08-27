<?php

declare(strict_types=1);

namespace HR\Identity\Presentation\Http\Resource;

use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class AuthenticatedUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'employee_id' => $this->employee?->id,
            'employee_number' => $this->employee?->employee_number,
        ];
    }
}
