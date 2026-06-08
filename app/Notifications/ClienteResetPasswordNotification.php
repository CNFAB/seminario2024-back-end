<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ClienteResetPasswordNotification extends Notification
{
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $resetUrl = $frontendUrl . '/cliente/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->correo);

        return (new MailMessage)
            ->from(env('MAIL_FROM_ADDRESS', 'cellsuport29@gmail.com'), env('MAIL_FROM_NAME', 'Sistema de reparaciones'))
            ->subject('Recuperación de contraseña - ReparaTech')
            ->greeting('¡Hola ' . $notifiable->nombre . '!')
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->action('Restablecer Contraseña', $resetUrl)
            ->line('Este enlace expirará en 60 minutos.')
            ->line('Si no solicitaste esto, ignora este mensaje.')
            ->saludo('Saludos, Equipo ReparaTech');
    }
}