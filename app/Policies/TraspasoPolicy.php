<?php

namespace App\Policies;

use App\Models\Traspaso;
use App\Models\User;

class TraspasoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Traspaso $traspaso): bool
    {
        if ($user->isAdministrador()) {
            return true;
        }

        return (int) $traspaso->user_id === (int) $user->id;
    }

    public function update(User $user, Traspaso $traspaso): bool
    {
        return $this->view($user, $traspaso);
    }

    public function delete(User $user, Traspaso $traspaso): bool
    {
        return $this->view($user, $traspaso);
    }

    /**
     * Aprobar / rechazar / recibir: mismo criterio que edición (creador o administrador).
     * Ajustar por sucursal destino si el flujo de negocio lo requiere.
     */
    public function aprobar(User $user, Traspaso $traspaso): bool
    {
        return $this->update($user, $traspaso);
    }

    public function recibir(User $user, Traspaso $traspaso): bool
    {
        return $this->update($user, $traspaso);
    }

    public function rechazar(User $user, Traspaso $traspaso): bool
    {
        return $this->update($user, $traspaso);
    }
}
