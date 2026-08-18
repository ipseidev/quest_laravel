<?php

namespace App\Policies;

use App\Models\Admin;

/**
 * Who may do what to a panel operator.
 *
 * This lives in a policy rather than in `AdminResource::canDelete()` because
 * Filament resolves authorization through the Gate: a static override on the
 * resource is consulted by some call sites and not by the delete button itself,
 * so the guard would read as present while the button stayed clickable. The
 * policy is consulted by every path — the table action, the edit page header, and
 * a request typed straight at the route.
 *
 * `admins` has no roles, so every operator may create and edit every other one.
 * The only refusals here are the two that cannot be undone.
 */
class AdminPolicy
{
    public function viewAny(Admin $operator): bool
    {
        return true;
    }

    public function view(Admin $operator, Admin $record): bool
    {
        return true;
    }

    public function create(Admin $operator): bool
    {
        return true;
    }

    public function update(Admin $operator, Admin $record): bool
    {
        return true;
    }

    /**
     * Deleting your own account signs you out permanently, and deleting the last
     * account locks everyone out of the panel with no way back in from the panel —
     * recovery means shell access to the server to run `nacre:admin`.
     */
    public function delete(Admin $operator, Admin $record): bool
    {
        if ($operator->getKey() === $record->getKey()) {
            return false;
        }

        return Admin::query()->count() > 1;
    }
}
