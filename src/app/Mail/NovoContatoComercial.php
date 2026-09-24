<?php

namespace App\Mail;

use App\Models\ContatoComercial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso interno: alguém pediu contato pela landing page.
 */
class NovoContatoComercial extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ContatoComercial $contato) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Novo pedido de contato — {$this->contato->municipio}",
            // Responder ao aviso já escreve para quem pediu contato.
            replyTo: [$this->contato->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.novo-contato');
    }
}
