<?php

namespace App\Policies;

use App\Models\Caja;
use App\Models\User;

class CajaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Caja $caja): bool
    {
        if ($user->isAdministrador()) {
            return true;
        }

        return (int) $caja->user_id === (int) $user->id;
    }

    public function update(User $user, Caja $caja): bool
    {
        return $this->view($user, $caja);
    }

    public function delete(User $user, Caja $caja): bool
    {
        return $this->view($user, $caja);
    }
}
