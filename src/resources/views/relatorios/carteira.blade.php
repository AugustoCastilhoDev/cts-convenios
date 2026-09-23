<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Carteira de convênios</title>
    @include('relatorios._estilo')
</head>
<body>
    <h1>Carteira de convênios</h1>
    <p class="sub">
        Gerado em {{ $geradoEm->format('d/m/Y H:i') }} por {{ $autor->name }} ({{ $autor->role->label() }})
        — {{ $convenios->count() }} {{ $convenios->count() === 1 ? 'convênio' : 'convênios' }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Número</th>
                @if ($exibirPrefeitura)<th>Prefeitura</th>@endif
                <th>Órgão concedente</th>
                <th>Objeto</th>
                <th>Etapa</th>
                <th class="num">Repasse</th>
                <th class="num">Contrapartida</th>
                <th class="num">Contratado</th>
                <th class="num">Saldo</th>
                <th>Vigência até</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($convenios as $c)
                @php $saldo = (float) $c->valor_repasse + (float) $c->valor_contrapartida - (float) $c->total_contratado; @endphp
                <tr>
                    <td>{{ $c->numero_convenio }}</td>
                    @if ($exibirPrefeitura)<td>{{ $c->tenant?->razao_social }}</td>@endif
                    <td>{{ $c->orgao_concedente }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($c->objeto, 90) }}</td>
                    <td>{{ $c->status->label() }}</td>
                    <td class="num">{{ number_format((float) $c->valor_repasse, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $c->valor_contrapartida, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $c->total_contratado, 2, ',', '.') }}</td>
                    <td class="num {{ $saldo < 0 ? 'neg' : '' }}">{{ number_format($saldo, 2, ',', '.') }}</td>
                    <td>{{ $c->data_vigencia_fim?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $exibirPrefeitura ? 10 : 9 }}" class="muted">Nenhum convênio encontrado para o filtro.</td></tr>
            @endforelse
        </tbody>
        @if ($convenios->isNotEmpty())
            <tfoot>
                <tr class="totais">
                    <td colspan="{{ $exibirPrefeitura ? 5 : 4 }}">Totais</td>
                    <td class="num">{{ number_format($totalRepasse, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalContrapartida, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalContratado, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalRepasse + $totalContrapartida - $totalContratado, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <p class="rodape">Valores em reais (R$). CTS Convênios — Castilho Soluções Digitais.</p>
</body>
</html>
