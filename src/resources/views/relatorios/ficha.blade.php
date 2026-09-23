@php
    $moeda = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $rotulos = [
        'numero_convenio' => 'Número', 'orgao_concedente' => 'Órgão concedente', 'objeto' => 'Objeto',
        'valor_repasse' => 'Repasse', 'valor_contrapartida' => 'Contrapartida', 'status' => 'Etapa',
        'data_assinatura' => 'Assinatura', 'data_vigencia_fim' => 'Fim da vigência',
        'prazo_prestacao_contas' => 'Prestação de contas', 'tenant_id' => 'Prefeitura', 'deleted_at' => 'Exclusão',
    ];
    $eventos = ['created' => 'Criado', 'updated' => 'Alterado', 'deleted' => 'Excluído', 'restored' => 'Restaurado'];
    $texto = fn ($v) => is_null($v) ? '—' : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ficha do convênio {{ $convenio->numero_convenio }}</title>
    @include('relatorios._estilo')
</head>
<body>
    <h1>Convênio {{ $convenio->numero_convenio }}</h1>
    <p class="sub">
        {{ $convenio->tenant?->razao_social }} — gerado em {{ $geradoEm->format('d/m/Y H:i') }}
        por {{ $autor->name }} ({{ $autor->role->label() }})
    </p>

    <table class="grade">
        <tr><td class="muted">Órgão concedente</td><td>{{ $convenio->orgao_concedente }}</td></tr>
        <tr><td class="muted">Objeto</td><td>{{ $convenio->objeto }}</td></tr>
        <tr><td class="muted">Etapa</td><td>{{ $convenio->status->label() }}</td></tr>
        <tr><td class="muted">Assinatura</td><td>{{ $convenio->data_assinatura?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="muted">Fim da vigência</td><td>{{ $convenio->data_vigencia_fim?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="muted">Prestação de contas</td><td>{{ $convenio->prazo_prestacao_contas?->format('d/m/Y') ?? '—' }}</td></tr>
    </table>

    <h2>Situação financeira</h2>
    <table class="grade">
        <tr><td class="muted">Repasse</td><td class="num">{{ $moeda($convenio->valor_repasse) }}</td></tr>
        <tr><td class="muted">Contrapartida</td><td class="num">{{ $moeda($convenio->valor_contrapartida) }}</td></tr>
        <tr><td class="muted">Total contratado</td><td class="num">{{ $moeda($convenio->total_contratado) }}</td></tr>
        <tr><td><strong>Saldo disponível</strong></td><td class="num {{ $saldo < 0 ? 'neg' : '' }}"><strong>{{ $moeda($saldo) }}</strong></td></tr>
    </table>

    <h2>Contratos vinculados</h2>
    <table>
        <thead><tr><th>Contrato</th><th>Empresa</th><th class="num">Valor</th><th>Execução</th></tr></thead>
        <tbody>
            @forelse ($convenio->contratosVinculados as $contrato)
                <tr>
                    <td>{{ $contrato->numero_contrato }}</td>
                    <td>{{ $contrato->empresa_contratada }}</td>
                    <td class="num">{{ $moeda($contrato->valor_contratado) }}</td>
                    <td>{{ $contrato->status_execucao->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nenhum contrato vinculado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Documentos anexados</h2>
    <table>
        <thead><tr><th>Arquivo</th><th>Tipo</th><th>Enviado em</th></tr></thead>
        <tbody>
            @forelse ($convenio->arquivos as $arquivo)
                <tr>
                    <td>{{ $arquivo->nome_original }}</td>
                    <td>{{ $arquivo->tipo_documento->label() }}</td>
                    <td>{{ $arquivo->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Nenhum documento anexado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Alertas de prazo</h2>
    <table>
        <thead><tr><th>Prazo</th><th>Marco</th><th>Data do prazo</th><th>Situação</th></tr></thead>
        <tbody>
            @forelse ($convenio->alertas->sortBy('created_at') as $alerta)
                <tr>
                    <td>{{ $alerta->tipo_prazo->label() }}</td>
                    <td>{{ $alerta->marco_dias === \App\Services\AlertaPrazoService::MARCO_VENCIDO ? 'Prazo vencido' : $alerta->marco_dias.' dias antes' }}</td>
                    <td>{{ $alerta->data_prazo->format('d/m/Y') }}</td>
                    <td>
                        @if ($alerta->enviado_em) Enviado em {{ $alerta->enviado_em->format('d/m/Y H:i') }}
                        @elseif ($alerta->cancelado_em) Cancelado
                        @else Pendente @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nenhum alerta gerado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Histórico de alterações (trilha de auditoria)</h2>
    <table>
        <thead><tr><th>Data</th><th>Usuário</th><th>Evento</th><th>Alterações</th></tr></thead>
        <tbody>
            @forelse ($historico as $registro)
                <tr>
                    <td class="num">{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $registro->user?->name ?? 'Sistema' }}</td>
                    <td>{{ $eventos[$registro->event] ?? $registro->event }}</td>
                    <td>
                        @if ($registro->event === 'updated')
                            @foreach ($registro->new_values as $campo => $novo)
                                {{ $rotulos[$campo] ?? $campo }}: {{ $texto($registro->old_values[$campo] ?? null) }} → {{ $texto($novo) }}<br>
                            @endforeach
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nenhuma alteração registrada.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($historico->count() >= 100)
        <p class="muted">Exibindo as 100 alterações mais recentes.</p>
    @endif

    <p class="rodape">CTS Convênios — Castilho Soluções Digitais. Documento gerado automaticamente a partir dos registros do sistema.</p>
</body>
</html>
