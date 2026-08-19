<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('roles')->orderBy('id')->get();

        return view('users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $user->load('roles.permissions');

        return view('users.show', compact('user'));
    }

    public function create(): View
    {
        return view('users.create', [
            'user' => new User(),
            'roles' => Role::orderBy('label')->get(),
            'selectedRoles' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // The User model casts `password` to `hashed`, so no Hash::make here.
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync($data['roles'] ?? []);

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$user->name}\" was created.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('label')->get(),
            'selectedRoles' => $user->roles->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $roleIds = $data['roles'] ?? [];
        $this->guardLastAdmin($user, $roleIds);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        // Blank password on the edit form means "leave it alone".
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->roles()->sync($roleIds);
        $user->forgetCachedPermissions();

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$user->name}\" was updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(request()->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Losing the last admin locks everyone out of an admin-only system,
        // and there is no way back in through the UI.
        $this->guardLastAdmin($user, []);

        $name = $user->name;
        $user->roles()->detach();
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$name}\" was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            // Required when creating, optional when editing.
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ]);
    }

    /**
     * Block a change that would remove the system's only remaining admin.
     *
     * @param  array<int, int|string>  $newRoleIds
     */
    private function guardLastAdmin(User $user, array $newRoleIds): void
    {
        if (! $user->isAdmin()) {
            return;
        }

        $adminRole = Role::where('name', Role::ADMIN)->first();

        if (! $adminRole) {
            return;
        }

        $keepsAdmin = in_array((string) $adminRole->id, array_map('strval', $newRoleIds), true);

        if ($keepsAdmin) {
            return;
        }

        $otherAdmins = $adminRole->users()->whereKeyNot($user->getKey())->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages([
                'roles' => 'This is the last administrator. Grant the administrator role to another user first.',
            ]);
        }
    }
}
