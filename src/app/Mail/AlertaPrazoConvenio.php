<?php

namespace App\Mail;

use App\Models\AlertaPrazo;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Enviado de forma síncrona pelo job EnviarAlertaPrazo (que já roda na
 * fila), por isso não implementa ShouldQueue.
 */
class AlertaPrazoConvenio extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<int, string>  $destinatariosReais  Preenchido só quando os alertas estão redirecionados (dev/homologação).
     */
    public function __construct(
        public readonly AlertaPrazo $alerta,
        public readonly int $dias,
        public readonly array $destinatariosReais = [],
    ) {}

    public function envelope(): Envelope
    {
        $convenio = $this->alerta->convenio;
        $prazo = $this->alerta->tipo_prazo->label();

        $assunto = $this->dias < 0
            ? "URGENTE: prazo vencido — Convênio {$convenio->numero_convenio}"
            : "Convênio {$convenio->numero_convenio}: {$prazo} em {$this->dias} ".($this->dias === 1 ? 'dia' : 'dias');

        return new Envelope(
            subject: ($this->destinatariosReais !== [] ? '[TESTE] ' : '').$assunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.alerta-prazo',
            with: [
                'convenio' => $this->alerta->convenio,
                'tenant' => $this->alerta->convenio->tenant,
                'tipo' => $this->alerta->tipo_prazo->label(),
                'dataPrazo' => $this->alerta->data_prazo->format('d/m/Y'),
                'dias' => $this->dias,
                'vencido' => $this->dias < 0,
                'venceHoje' => $this->dias === 0,
                'destinatariosReais' => $this->destinatariosReais,
            ],
        );
    }
}
