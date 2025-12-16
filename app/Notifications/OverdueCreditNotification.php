<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueCreditNotification extends Notification
{
    use Queueable;

    protected $cuota;
    protected $cliente;

    /**
     * Create a new notification instance.
     */
    public function __construct($cuota, $cliente)
    {
        $this->cuota = $cuota;
        $this->cliente = $cliente;
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
        $diasVencidos = now()->diffInDays($this->cuota->fecha_vencimiento, false);
        $mensaje = "La cuota #{$this->cuota->numero_cuota} del cliente '{$this->cliente->nombre}' está vencida hace " . abs($diasVencidos) . " días. Monto: $" . number_format($this->cuota->monto, 2);

        return [
            'title' => 'Cuota de Crédito Vencida',
            'message' => $mensaje,
            'type' => 'error',
            'cuota_id' => $this->cuota->id,
            'cliente_id' => $this->cliente->id,
        ];
    }
}
