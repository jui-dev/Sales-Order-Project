<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // One dynamic gate resolves every permission name, rather than a
        // Gate::define() per permission - which would mean querying the
        // permissions table on every boot, including during migrations.
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            // Returning null (not false) on a miss lets the check fall through
            // to any policy that may be registered for the ability later.
            return $this->userHasPermission($user, $ability) ?: null;
        });
    }

    /**
     * Resolve a permission without letting a missing schema fatal the app.
     *
     * A fresh clone runs migrations against a database where these tables do
     * not exist yet, and the gate is consulted before that finishes.
     */
    private function userHasPermission(User $user, string $ability): bool
    {
        try {
            if (! Schema::hasTable('permissions')) {
                return false;
            }

            return $user->hasPermission($ability);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
