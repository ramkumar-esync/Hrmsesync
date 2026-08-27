<?php

declare(strict_types=1);

namespace HR\Identity\Infrastructure\Persistence\Eloquent;

use HR\Employee\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use HR\Identity\Domain\Enum\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The account used to sign in. Deliberately thin: everything about the person
 * as an employee lives in the Employee context.
 *
 * @property string $id
 * @property string $email
 * @property string $password
 * @property Role $role
 * @property bool $is_active
 */
final class User extends Authenticatable
{
    use HasApiTokens;
    use HasUuids;
    use Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    private ?string $cachedEmployeeId = null;

    private bool $employeeIdResolved = false;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(EmployeeRecord::class, 'user_id');
    }

    /**
     * The linked employee's id, or null if this account has no employee record.
     *
     * Policies and middleware receive the user straight from the auth guard,
     * with no relations loaded. Reading `$this->employee` there would trip
     * Model::preventLazyLoading(), so this fetches the single column directly
     * and remembers it for the rest of the request.
     */
    public function employeeId(): ?string
    {
        if ($this->employeeIdResolved) {
            return $this->cachedEmployeeId;
        }

        $this->cachedEmployeeId = $this->relationLoaded('employee')
            ? $this->employee?->id
            : $this->employee()->value('id');

        $this->employeeIdResolved = true;

        return $this->cachedEmployeeId;
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
