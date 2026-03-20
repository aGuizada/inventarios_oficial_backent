<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

/**
 * Autorización centralizada para ventas (sustituye comprobaciones dispersas en controladores).
 */
class VentaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Registrar venta: cualquier usuario autenticado con acceso a la API de ventas.
     * (Ajustar por rol/sucursal si el negocio lo exige.)
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Venta $venta): bool
    {
        if ($user->isAdministrador()) {
            return true;
        }

        return (int) $venta->user_id === (int) $user->id;
    }

    /**
     * ¿Puede intentar anular? (el estado Activo se valida en el controlador → 422).
     */
    public function anular(User $user, Venta $venta): bool
    {
        return $this->view($user, $venta);
    }

    public function update(User $user, Venta $venta): bool
    {
        return $this->view($user, $venta);
    }

    public function delete(User $user, Venta $venta): bool
    {
        return $this->view($user, $venta);
    }
}
