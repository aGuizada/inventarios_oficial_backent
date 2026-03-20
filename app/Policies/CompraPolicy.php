<?php

namespace App\Policies;

use App\Models\CompraBase;
use App\Models\User;

class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, CompraBase $compra): bool
    {
        if ($user->isAdministrador()) {
            return true;
        }

        return (int) $compra->user_id === (int) $user->id;
    }

    public function update(User $user, CompraBase $compra): bool
    {
        return $this->view($user, $compra);
    }

    public function delete(User $user, CompraBase $compra): bool
    {
        return $this->view($user, $compra);
    }
}
