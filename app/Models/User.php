<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Permission names granted by this user's roles, resolved once per instance.
     *
     * The sidebar runs a @can check per nav group, so without this the roles and
     * permissions tables would be re-queried a dozen times on every page render.
     *
     * @var Collection<int, string>|null
     */
    protected ?Collection $permissionNames = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $name): bool
    {
        return $this->roles->contains('name', $name);
    }

    /**
     * Admins bypass every authorization check (see AuthServiceProvider).
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissionNames()->contains($name);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        return $this->permissionNames ??= $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * Drop the memoized permissions so the next check re-reads the database.
     *
     * Needed after roles are reassigned within a single request - otherwise the
     * user-management screens would keep authorizing against the old role set.
     */
    public function forgetCachedPermissions(): self
    {
        $this->permissionNames = null;
        $this->unsetRelation('roles');

        return $this;
    }
}
