<?php
namespace App\Notifications;

use App\Models\ReparacionMultiple;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReparacionMultipleEstadoChanged extends Notification
{
    protected $reparacionMultiple;
    protected $estadoAnterior;
    protected $estadoNuevo;

    public function __construct(ReparacionMultiple $reparacionMultiple, string $estadoAnterior, string $estadoNuevo)
    {
        $this->reparacionMultiple = $reparacionMultiple;
        $this->estadoAnterior     = $estadoAnterior;
        $this->estadoNuevo        = $estadoNuevo;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $estadosMap = [
            'PENDIENTE'       => 'Pendiente',
            'EN_REPARACION'   => 'En reparacion',
            'TERMINADO'       => 'Terminado',
            'ESPERANDO_PIEZA' => 'Esperando pieza',
            'CANCELADO'       => 'Cancelado',
        ];

        $estadoAnteriorTexto = $estadosMap[$this->estadoAnterior] ?? $this->estadoAnterior;
        $estadoNuevoTexto    = $estadosMap[$this->estadoNuevo]    ?? $this->estadoNuevo;

        $reparacion  = $this->reparacionMultiple->reparacion;
        $diagnostico = $reparacion?->diagnostico;
        $ingreso     = $diagnostico?->ingreso;
        $pieza       = $this->reparacionMultiple->pieza;

        $marca  = $ingreso?->dispositivo?->modelo?->marca?->marca       ?? '';
        $modelo = $ingreso?->dispositivo?->modelo?->nombre_modelo        ?? 'No especificado';
        $equipo = trim("{$marca} {$modelo}");

        $nombrePieza = $pieza?->nombre_pieza ?? 'No especificada';

        return (new MailMessage)
            ->subject("Cambio de estado - Reparacion #{$this->reparacionMultiple->id_multiple}")
            ->greeting("Hola {$notifiable->nombre}!")
            ->line("La reparacion de tu equipo ha cambiado de estado:")
            ->line("Estado anterior: {$estadoAnteriorTexto}")
            ->line("Estado actual: {$estadoNuevoTexto}")
            ->line("Equipo: {$equipo}")
            ->line("Pieza: {$nombrePieza}")
            ->when($this->reparacionMultiple->comentario_tecnico, function ($mail) {
                $comentario = $this->reparacionMultiple->comentario_tecnico;
                return $mail->line("Comentario del tecnico: {$comentario}");
            })
            ->when($this->reparacionMultiple->precio_total, function ($mail) {
                $precio = $this->reparacionMultiple->precio_total;
                return $mail->line("Precio total: {$precio}");
            })
            ->action('Ver reparacion', url("/reparaciones/{$this->reparacionMultiple->id_multiple}"))
            ->line("Gracias por confiar en nosotros.");
    }

   public function toArray(object $notifiable): array
{
    $estadosMap = [
        'PENDIENTE'       => 'Pendiente',
        'EN_REPARACION'   => 'En reparacion',
        'TERMINADO'       => 'Terminado',
        'ESPERANDO_PIEZA' => 'Esperando pieza',
        'CANCELADO'       => 'Cancelado',
    ];

    $textoAnterior = $estadosMap[$this->estadoAnterior] ?? $this->estadoAnterior;
    $textoNuevo    = $estadosMap[$this->estadoNuevo]    ?? $this->estadoNuevo;

    return [
        'reparacion_multiple_id' => $this->reparacionMultiple->id_multiple,
        'estado_anterior'        => $this->estadoAnterior,
        'estado_nuevo'           => $this->estadoNuevo,
        'mensaje'                => "La reparacion cambio de {$textoAnterior} a {$textoNuevo}",
        'fecha'                  => now()->toDateTimeString(),
    ];
}
}