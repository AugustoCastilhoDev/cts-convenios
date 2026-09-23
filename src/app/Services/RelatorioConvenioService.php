<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relatórios para o Fiscal de Controle Interno e auditorias do TCE: a
 * carteira de convênios (CSV/PDF) e a ficha completa de um convênio (PDF).
 * Cada exportação fica registrada no log com quem pediu e o quê.
 */
class RelatorioConvenioService
{
    /** Quantas alterações da auditoria entram na ficha (as mais recentes). */
    private const LIMITE_HISTORICO = 100;

    /**
     * Sem subconjunto de fontes, o dompdf embute a fonte inteira em cada PDF
     * (~880 KB); com ele, só os caracteres usados.
     */
    private const OPCOES_PDF = ['enable_font_subsetting' => true];

    private const CABECALHO_CSV = [
        'Número', 'Órgão concedente', 'Objeto', 'Etapa', 'Repasse (R$)', 'Contrapartida (R$)',
        'Contratado (R$)', 'Saldo disponível (R$)', 'Assinatura', 'Fim da vigência', 'Prestação de contas',
    ];

    public function carteira(?string $status, ?string $busca): Collection
    {
        return Convenio::query()
            ->with('tenant')
            ->withSum('contratosVinculados as total_contratado', 'valor_contratado')
            ->filtrar($status, $busca)
            ->orderBy('numero_convenio')
            ->get();
    }

    public function carteiraCsv(User $autor, ?string $status, ?string $busca): StreamedResponse
    {
        $convenios = $this->carteira($status, $busca);
        $this->registrar($autor, 'carteira_csv', $convenios->count(), $status, $busca);

        return response()->streamDownload(function () use ($convenios) {
            $saida = fopen('php://output', 'w');

            // BOM para o Excel reconhecer UTF-8; ";" e vírgula decimal são o padrão do Excel em pt-BR.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, self::CABECALHO_CSV, ';');

            foreach ($convenios as $convenio) {
                fputcsv($saida, [
                    $this->textoSeguro($convenio->numero_convenio),
                    $this->textoSeguro($convenio->orgao_concedente),
                    $this->textoSeguro($convenio->objeto),
                    $convenio->status->label(),
                    $this->numero((float) $convenio->valor_repasse),
                    $this->numero((float) $convenio->valor_contrapartida),
                    $this->numero((float) $convenio->total_contratado),
                    $this->numero($this->saldo($convenio)),
                    $convenio->data_assinatura?->format('d/m/Y'),
                    $convenio->data_vigencia_fim?->format('d/m/Y'),
                    $convenio->prazo_prestacao_contas?->format('d/m/Y'),
                ], ';');
            }

            fclose($saida);
        }, 'convenios-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function carteiraPdf(User $autor, ?string $status, ?string $busca): Response
    {
        $convenios = $this->carteira($status, $busca);
        $this->registrar($autor, 'carteira_pdf', $convenios->count(), $status, $busca);

        return Pdf::loadView('relatorios.carteira', [
            'convenios' => $convenios,
            'autor' => $autor,
            'geradoEm' => now(),
            'totalRepasse' => $convenios->sum(fn (Convenio $c) => (float) $c->valor_repasse),
            'totalContrapartida' => $convenios->sum(fn (Convenio $c) => (float) $c->valor_contrapartida),
            'totalContratado' => $convenios->sum(fn (Convenio $c) => (float) $c->total_contratado),
            'exibirPrefeitura' => $autor->isAdministradorInterno(),
        ])->setOptions(self::OPCOES_PDF)
            ->setPaper('a4', 'landscape')
            ->download('convenios-'.now()->format('Y-m-d').'.pdf');
    }

    public function fichaPdf(User $autor, Convenio $convenio): Response
    {
        $convenio->load(['tenant', 'contratosVinculados', 'alertas', 'arquivos'])
            ->loadSum('contratosVinculados as total_contratado', 'valor_contratado');

        $historico = Audit::query()
            ->with('user')
            ->where('auditable_type', $convenio->getMorphClass())
            ->where('auditable_id', $convenio->id)
            ->latest('created_at')
            ->limit(self::LIMITE_HISTORICO)
            ->get();

        $this->registrar($autor, 'ficha_pdf', 1, null, $convenio->numero_convenio);

        return Pdf::loadView('relatorios.ficha', [
            'convenio' => $convenio,
            'saldo' => $this->saldo($convenio),
            'historico' => $historico,
            'autor' => $autor,
            'geradoEm' => now(),
        ])->setOptions(self::OPCOES_PDF)
            ->setPaper('a4')
            ->download('convenio-'.str_replace(['/', '\\'], '-', $convenio->numero_convenio).'.pdf');
    }

    private function saldo(Convenio $convenio): float
    {
        return (float) $convenio->valor_repasse + (float) $convenio->valor_contrapartida
            - (float) ($convenio->total_contratado ?? 0);
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 2, ',', '');
    }

    /**
     * Impede "injeção de fórmula": um texto começando com = + - @ seria
     * executado como fórmula ao abrir o CSV no Excel.
     */
    private function textoSeguro(?string $texto): string
    {
        $texto = (string) $texto;

        return preg_match('/^[=+\-@\t\r]/', $texto) ? "'".$texto : $texto;
    }

    private function registrar(User $autor, string $relatorio, int $registros, ?string $status, ?string $busca): void
    {
        Log::info('Relatório exportado', [
            'relatorio' => $relatorio,
            'user_id' => $autor->id,
            'tenant_id' => $autor->tenant_id,
            'registros' => $registros,
            'filtro_status' => $status,
            'filtro_busca' => $busca,
        ]);
    }
}
