<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CuotaCredito;
use App\Notifications\OverdueCreditNotification;
use App\Helpers\NotificationHelper;
use Carbon\Carbon;

class CheckOverdueCredits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue credit payments and notify administrators';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue credit payments...');

        // Get all unpaid quotas with past due dates
        $overdueCuotas = CuotaCredito::where('pagado', false)
            ->where('fecha_vencimiento', '<', Carbon::today())
            ->with(['creditoVenta.cliente'])
            ->get();

        if ($overdueCuotas->isEmpty()) {
            $this->info('No overdue credits found.');
            return 0;
        }

        $this->info("Found {$overdueCuotas->count()} overdue credit payments.");

        foreach ($overdueCuotas as $cuota) {
            $cliente = $cuota->creditoVenta?->cliente;

            if (!$cliente) {
                continue;
            }

            // Notify administrators
            NotificationHelper::notifyAdmins(new OverdueCreditNotification($cuota, $cliente));

            $this->line("Notified admins about overdue payment for client: {$cliente->nombre}");
        }

        $this->info('✓ Overdue credit check completed successfully.');
        return 0;
    }
}
