<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail com o link para criar uma nova senha. Vai pela fila (o pedido não espera o envio) e o
 * link aponta para a tela do sistema, que envia o token de volta a /api/redefinir-senha.
 */
class RedefinirSenha extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        // Sempre o endereço oficial (APP_URL), nunca o Host da requisição: com o cabeçalho Host forjado no
        // pedido, o link do e-mail apontaria para o site de um atacante e o token vazaria no clique.
        $link = rtrim((string) config('app.url'), '/').'/app/redefinir-senha?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $redirecionado = filled(config('alertas.redirecionar_para'));

        $mensagem = (new MailMessage)
            ->subject(($redirecionado ? '[TESTE] ' : '').'Redefinição de senha — CTS Convênios')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Recebemos um pedido para criar uma nova senha de acesso ao CTS Convênios.')
            ->action('Criar nova senha', $link)
            ->line("Este link vale por {$minutos} minutos e só pode ser usado uma vez.")
            ->line('Se você não pediu isso, ignore este e-mail: sua senha continua a mesma e ninguém consegue entrar sem ela.')
            ->salutation('Castilho Soluções Digitais');

        if ($redirecionado) {
            $mensagem->line("Modo de teste: este e-mail foi redirecionado. Destinatário real em produção: {$notifiable->email}.");
        }

        return $mensagem;
    }
}
