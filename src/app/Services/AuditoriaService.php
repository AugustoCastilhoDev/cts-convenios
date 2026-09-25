<?php

namespace App\Services;

use App\Http\Controllers\Api\AuditController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta e exportação da trilha de auditoria. O escopo por prefeitura mora AQUI (um lugar só), para a
 * tela e a planilha nunca divergirem: o super administrador enxerga tudo; o administrador da prefeitura,
 * só o que aconteceu na própria prefeitura.
 */
class AuditoriaService
{
    private const CABECALHO_CSV = ['Data e hora', 'Prefeitura', 'Usuário', 'E-mail do usuário', 'Evento', 'Tipo de registro', 'ID do registro', 'Alterações', 'IP'];

    private const EVENTOS = ['created' => 'Criado', 'updated' => 'Alterado', 'deleted' => 'Excluído', 'restored' => 'Restaurado'];

    private const TIPOS = ['convenio' => 'Convênio', 'contrato' => 'Contrato', 'arquivo' => 'Documento', 'prefeitura' => 'Prefeitura', 'usuario' => 'Usuário'];

    /** Mesmos rótulos da tela (resources/js/utils/auditoria.js). */
    private const CAMPOS = [
        'numero_convenio' => 'Número', 'orgao_concedente' => 'Órgão concedente', 'objeto' => 'Objeto', 'secretaria' => 'Secretaria',
        'valor_repasse' => 'Repasse', 'valor_contrapartida' => 'Contrapartida', 'status' => 'Etapa', 'data_assinatura' => 'Assinatura',
        'data_vigencia_fim' => 'Fim da vigência', 'prazo_prestacao_contas' => 'Prestação de contas', 'numero_contrato' => 'Nº do contrato',
        'empresa_contratada' => 'Empresa', 'valor_contratado' => 'Valor contratado', 'status_execucao' => 'Execução',
        'tipo_documento' => 'Tipo do documento', 'nome_original' => 'Arquivo', 'tamanho_bytes' => 'Tamanho (bytes)',
        'razao_social' => 'Razão social', 'cnpj' => 'CNPJ', 'active' => 'Ativo', 'must_change_password' => 'Troca de senha obrigatória',
        'name' => 'Nome', 'email' => 'E-mail', 'role' => 'Papel', 'deleted_at' => 'Exclusão',
    ];

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Audit>
     */
    public function consultar(array $filtros, User $autor): Builder
    {
        return Audit::query()
            ->with('user')
            // Administrador da prefeitura: só a própria prefeitura, qualquer que seja o filtro enviado.
            ->when(! $autor->isAdministradorInterno(), fn (Builder $query) => $query->where('tenant_id', $autor->tenant_id))
            ->when($filtros['tipo'] ?? null, fn (Builder $query, $tipo) => $query->where('auditable_type', AuditController::TIPOS[$tipo]))
            ->when($filtros['registro_id'] ?? null, fn (Builder $query, $id) => $query->where('auditable_id', $id))
            ->when($filtros['user_id'] ?? null, fn (Builder $query, $id) => $query->where('user_id', $id))
            ->when($filtros['evento'] ?? null, fn (Builder $query, $evento) => $query->where('event', $evento))
            ->when($filtros['de'] ?? null, fn (Builder $query, $data) => $query->where('created_at', '>=', $data.' 00:00:00'))
            ->when($filtros['ate'] ?? null, fn (Builder $query, $data) => $query->where('created_at', '<=', $data.' 23:59:59'))
            ->latest('created_at')
            ->latest('id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function exportarCsv(array $filtros, User $autor): StreamedResponse
    {
        $consulta = $this->consultar($filtros, $autor);

        Log::info('Auditoria exportada', ['user_id' => $autor->id, 'tenant_id' => $autor->tenant_id, 'filtros' => $filtros]);

        // Nome da prefeitura de cada registro (poucas linhas: uma consulta só, em vez de uma por registro).
        $prefeituras = Tenant::query()->withoutGlobalScopes()->pluck('razao_social', 'id');

        return response()->streamDownload(function () use ($consulta, $prefeituras) {
            $saida = fopen('php://output', 'w');

            // BOM para o Excel reconhecer UTF-8; ";" é o separador padrão do Excel em pt-BR.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, self::CABECALHO_CSV, ';');

            // lazy() lê em blocos: uma auditoria grande não vai inteira para a memória.
            foreach ($consulta->lazy(1000) as $auditoria) {
                fputcsv($saida, [
                    $auditoria->created_at->format('d/m/Y H:i:s'),
                    $this->textoSeguro($prefeituras->get($auditoria->tenant_id)),
                    $this->textoSeguro($auditoria->user?->name ?? 'Sistema'),
                    $this->textoSeguro($auditoria->user?->email),
                    self::EVENTOS[$auditoria->event] ?? $auditoria->event,
                    self::TIPOS[array_search($auditoria->auditable_type, AuditController::TIPOS, true)] ?? class_basename($auditoria->auditable_type),
                    $auditoria->auditable_id,
                    $this->textoSeguro($this->alteracoes($auditoria)),
                    $auditoria->ip_address,
                ], ';');
            }

            fclose($saida);
        }, 'auditoria-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** "Campo: antes → depois" para alterações; "Campo: valor" para criação e exclusão. */
    private function alteracoes(Audit $auditoria): string
    {
        $novos = $auditoria->new_values ?? [];
        $antigos = $auditoria->old_values ?? [];
        $campos = array_values(array_unique(array_merge(array_keys($novos), array_keys($antigos))));
        $partes = [];

        foreach ($campos as $campo) {
            // A prefeitura já vai numa coluna própria (com o nome, não o identificador).
            if (in_array($campo, ['id', 'tenant_id'], true)) {
                continue;
            }

            $rotulo = self::CAMPOS[$campo] ?? $campo;
            $partes[] = $auditoria->event === 'updated'
                ? "{$rotulo}: {$this->valor($antigos[$campo] ?? null)} → {$this->valor($novos[$campo] ?? null)}"
                : "{$rotulo}: {$this->valor($novos[$campo] ?? $antigos[$campo] ?? null)}";
        }

        return implode('; ', $partes);
    }

    private function valor(mixed $valor): string
    {
        return match (true) {
            $valor === null || $valor === '' => '—',
            is_bool($valor) => $valor ? 'sim' : 'não',
            is_array($valor) => (string) json_encode($valor, JSON_UNESCAPED_UNICODE),
            default => (string) $valor,
        };
    }

    /**
     * Impede "injeção de fórmula": um texto começando com = + - @ seria
     * executado como fórmula ao abrir o CSV no Excel. Os valores auditados vêm de digitação de usuários.
     */
    private function textoSeguro(?string $texto): string
    {
        $texto = (string) $texto;

        return preg_match('/^[=+\-@\t\r]/', $texto) ? "'".$texto : $texto;
    }
}
