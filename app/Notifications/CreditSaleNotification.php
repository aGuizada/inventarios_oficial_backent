<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreditSaleNotification extends Notification
{
    use Queueable;

    protected $venta;
    protected $cliente;

    /**
     * Create a new notification instance.
     */
    public function __construct($venta, $cliente)
    {
        $this->venta = $venta;
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
        return [
            'title' => 'Nueva Venta a Crédito',
            'message' => "Se registró una venta a crédito para '{$this->cliente->nombre}'. Monto total: $" . number_format($this->venta->total, 2),
            'type' => 'info',
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
        ];
    }
}
