<?php
namespace App\Notifications;

use App\Models\Diagnostico;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DiagnosticoEstadoChanged extends Notification
{
    protected $diagnostico;
    protected $estadoAnterior;
    protected $estadoNuevo;

    public function __construct(Diagnostico $diagnostico, string $estadoAnterior, string $estadoNuevo)
    {
        $this->diagnostico    = $diagnostico;
        $this->estadoAnterior = $estadoAnterior;
        $this->estadoNuevo    = $estadoNuevo;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $estadosMap = [
            'ESPERANDO_DIAGNOSTICO' => 'Esperando diagnostico',
            'ESPERANDO_APROBACION'  => 'Esperando aprobacion',
            'NO_REPARADO'           => 'No se reparara',
            'EN_REPARACION'         => 'En reparacion',
            'EN_ESPERA_DE_PIEZAS'   => 'En espera de piezas',
            'LISTO_PARA_RETIRAR'    => 'Listo para retirar',
            'APROBADO'              => 'Aprobado',
            'RECHAZADO'             => 'Rechazado',
        ];

        $estadoAnteriorTexto = $estadosMap[$this->estadoAnterior] ?? $this->estadoAnterior;
        $estadoNuevoTexto    = $estadosMap[$this->estadoNuevo]    ?? $this->estadoNuevo;
        $ingreso             = $this->diagnostico->ingreso;

        return (new MailMessage)
            ->subject("Cambio de estado - Diagnostico #{$this->diagnostico->id_diagnostico}")
            ->greeting("Hola {$notifiable->nombre}!")
            ->line("El diagnostico ha cambiado de estado:")
            ->line("De: {$estadoAnteriorTexto}")
            ->line("A: {$estadoNuevoTexto}")
           ->when($ingreso, function ($mail) use ($ingreso) {
                $marca  = $ingreso->dispositivo?->modelo?->marca?->marca   ?? '';
                $modelo = $ingreso->dispositivo?->modelo?->nombre_modelo   ?? 'No especificado';
                $equipo = trim("{$marca} {$modelo}");
              return $mail->line("Equipo: {$equipo}");
})
            ->when($this->diagnostico->observacion, function ($mail) {
                $obs = $this->diagnostico->observacion;
                return $mail->line("Observacion: {$obs}");
            })
            ->action('Ver diagnostico', url("/diagnosticos/{$this->diagnostico->id_diagnostico}"))
            ->line("Gracias por confiar en nosotros.");
    }  // ← este } cerraba toMail y faltaba

    public function toArray(object $notifiable): array
    {
        return [
            'diagnostico_id'  => $this->diagnostico->id_diagnostico,
            'estado_anterior' => $this->estadoAnterior,
            'estado_nuevo'    => $this->estadoNuevo,
            'mensaje'         => "Diagnostico cambio de {$this->estadoAnterior} a {$this->estadoNuevo}",
            'fecha'           => now()->toDateTimeString(),
        ];
    }
}