<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;


class UserService
{
    /**
     * List users with filters and pagination.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('department')) {
            $query->where('departmen', 'like', '%'.$request->input('department').'%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');

            $query->where(function (Builder $q) use ($search) {
                $q->where('login', 'like',  "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_ud', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')
                ->paginate($request->input('per_page', config('helper.default_pagination_length')));
    }

    public function update(User $user, array $user_data): User
    {
        if (isset($user_data['password'])) {
            $user_data['password'] = bcrypt($user_data['password']);
            $user_data['password_changed_at'] = Carbon::now();
        }

        $user->update($user_data);
        return $user;
    }

    /**
     * Delete (soft delete) a user.
     *
     * @param User $user The user to be deleted.
     * @param User|null $currentUser The user performing the deletion.
     *
     * @return bool True if the user was deleted, false otherwise.
     *
     * @throws \Exception If the user is trying to delete themselves, or if the user is an admin.
     * @throws \Exception If the user is already deleted.
     */
    public function delete(User $user, ?User $currentUser = null): bool|null
    {
        if ($currentUser && $user->id === $currentUser->id) {
            throw new \Exception('You cannot delete yourself.');
        }

        if ($user->role === 'admin') {
            throw new \Exception('You cannot delete an admin user.');
        }

        if ($user->trashed()) {
            throw new \Exception('User already deleted.');
        }

        return $user->delete();
    }

    /**
     * Activate a user (restore if soft‑deleted, set active, unlock).
     *
     * @param User $user The user to be activated.
     * @param User|null $currentUser The user performing the activation.
     *
     * @throws \Exception If the user is trying to activate themselves, or if the user is an admin.
     * @throws \Exception If the user is already activated.
     */
    public function activate(User $user, ?User $currentUser = null): void
    {
        if ($currentUser && $user->id === $currentUser->id) {
            throw new \Exception('You cannot activate yourself.');
        }

        if ($user->is_active && !$user->trashed()) {
            throw new \Exception('User has already activated.');
        }

        $user->update([
            'is_active' => true,
            'is_locked' => false,
            'failed_login_attempts' => 0
        ]);

        if ($user->trashed()) {
            $user->restore();
        }
    }

    /**
     * Deactivate a user (deactivate, unlock, reset failed login attempts and delete all tokens).
     *
     * @param User $user The user to be deactivated.
     * @param User|null $currentUser The user performing the deactivation.
     *
     * @throws \Exception If the user is trying to deactivate themselves, or if the user is an admin.
     */
    public function deactivate(User $user, ?User $currentUser = null): void
    {
        if ($currentUser && $user->id === $currentUser->id) {
            throw new \Exception('You cannot deactivate your own account.');
        }

        if ($user->role == 'admin') {
            throw new \Exception('You cannot deactivate an admin account.');
        }

        if (!$user->is_active && !$user->trashed()) {
            throw new \Exception('User has already deactivated.');
        }

        $user->update([
            'is_active' => false,
            'is_locked' => false,
            'failed_login_attempts' => 0
        ]);

        $user->tokens()->delete();
    }
}