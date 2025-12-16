<?php

namespace App\Observers;

use App\Models\Traspaso;
use App\Notifications\PendingTransferNotification;
use App\Helpers\NotificationHelper;

class TraspasoObserver
{
    /**
     * Handle the Traspaso "created" event.
     */
    public function created(Traspaso $traspaso): void
    {
        // Notify admins when a new transfer is created and is pending approval
        if ($traspaso->estado === 'pendiente') {
            $traspaso->load(['almacenOrigen', 'almacenDestino', 'detalles']);
            NotificationHelper::notifyAdmins(new PendingTransferNotification($traspaso));
        }
    }

    /**
     * Handle the Traspaso "updated" event.
     */
    public function updated(Traspaso $traspaso): void
    {
        // If the status changes to pending, notify again
        if ($traspaso->isDirty('estado') && $traspaso->estado === 'pendiente') {
            $traspaso->load(['almacenOrigen', 'almacenDestino', 'detalles']);
            NotificationHelper::notifyAdmins(new PendingTransferNotification($traspaso));
        }
    }
}
