<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingTransferNotification extends Notification
{
    use Queueable;

    protected $traspaso;

    /**
     * Create a new notification instance.
     */
    public function __construct($traspaso)
    {
        $this->traspaso = $traspaso;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $almacenOrigen = $this->traspaso->almacenOrigen->nombre ?? 'Desconocido';
        $almacenDestino = $this->traspaso->almacenDestino->nombre ?? 'Desconocido';

        return [
            'title' => 'Traspaso Pendiente de Aprobación',
            'message' => "Hay un traspaso pendiente desde '{$almacenOrigen}' hacia '{$almacenDestino}'. Total de artículos: " . ($this->traspaso->detalles()->count() ?? 0),
            'type' => 'warning',
            'traspaso_id' => $this->traspaso->id,
        ];
    }
}
