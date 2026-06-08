<?php
// app/Console/Commands/ExpirarDiagnosticos.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Diagnostico;
use Carbon\Carbon;

class ExpirarDiagnosticos extends Command
{
    protected $signature   = 'diagnosticos:expirar';
    protected $description = 'Marca como EXPIRADO los diagnósticos vencidos que siguen en ESPERANDO_APROBACION';

    public function handle(): void
    {
        $cantidad = Diagnostico::where('estado', 'ESPERANDO_APROBACION')
            ->whereNotNull('fecha_expiracion')
            ->where('fecha_expiracion', '<', Carbon::now())
            ->update(['estado' => 'EXPIRADO']);

        $this->info("$cantidad diagnóstico(s) marcados como EXPIRADO.");
        \Log::info("Cron ExpirarDiagnosticos: $cantidad diagnósticos expirados.");
    }
}

