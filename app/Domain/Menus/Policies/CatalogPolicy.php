<?php

namespace App\Domain\Menus\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Business-level menu catalogue (categories and items) is managed by owners. */
class CatalogPolicy
{
    public function update(User $user, Model $model): bool
    {
        return $user->isOwnerOf($model->business);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }
}
