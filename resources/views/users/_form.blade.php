{{--
    Shared by users/create and users/edit.
    Expects: $user, $roles, $selectedRoles, $isEdit
--}}
<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text"
               id="name"
               name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $user->name) }}"
               required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email"
               id="email"
               name="email"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $user->email) }}"
               required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">
            Password @unless($isEdit)<span class="text-danger">*</span>@endunless
        </label>
        <input type="password"
               id="password"
               name="password"
               class="form-control @error('password') is-invalid @enderror"
               autocomplete="new-password"
               @unless($isEdit) required @endunless>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">
            @if($isEdit)
                Leave blank to keep the current password. Minimum 8 characters otherwise.
            @else
                Minimum 8 characters.
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <input type="password"
               id="password_confirmation"
               name="password_confirmation"
               class="form-control"
               autocomplete="new-password"
               @unless($isEdit) required @endunless>
    </div>
</div>

<hr class="my-4">

<h2 class="h6 fw-semibold mb-1">Roles</h2>
<p class="text-muted small mb-3">
    A user gets every permission held by any of their roles. Administrators bypass all checks.
</p>

@error('roles')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

<div class="row g-3">
    @forelse ($roles as $role)
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               name="roles[]"
                               value="{{ $role->id }}"
                               id="role-{{ $role->id }}"
                               {{ in_array($role->id, old('roles', $selectedRoles)) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="role-{{ $role->id }}">
                            {{ $role->label }}
                        </label>
                    </div>
                    @if($role->description)
                        <div class="text-muted small mt-1 ms-4">{{ $role->description }}</div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-warning mb-0">
                No roles exist yet. Run <code>php artisan db:seed --class=RolePermissionSeeder</code>.
            </div>
        </div>
    @endforelse
</div>
