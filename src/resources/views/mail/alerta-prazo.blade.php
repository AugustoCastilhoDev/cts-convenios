<x-mail::message>
@if ($vencido)
# Prazo vencido

O prazo abaixo **venceu há {{ abs($dias) }} {{ abs($dias) === 1 ? 'dia' : 'dias' }}**. Prazos de prestação de contas perdidos podem levar o município à inscrição no CADIN e ao bloqueio de novos repasses. Regularize com urgência.
@elseif ($venceHoje)
# O prazo vence hoje

**{{ $tipo }}** do convênio abaixo vence **hoje**.
@else
# Faltam {{ $dias }} {{ $dias === 1 ? 'dia' : 'dias' }} para o prazo

**{{ $tipo }}** do convênio abaixo vence em **{{ $dataPrazo }}**.
@endif

<x-mail::table>
| Convênio | Detalhes |
| :-- | :-- |
| Número | {{ $convenio->numero_convenio }} |
| Órgão concedente | {{ $convenio->orgao_concedente }} |
| Objeto | {{ \Illuminate\Support\Str::limit($convenio->objeto, 140) }} |
| Prazo | {{ $tipo }} |
| Data limite | {{ $dataPrazo }} |
| Situação | {{ $convenio->status->label() }} |
</x-mail::table>

Este aviso foi enviado automaticamente pelo CTS Convênios à equipe de {{ $tenant->razao_social }}.

@if ($destinatariosReais !== [])
> **Modo de teste:** este e-mail foi redirecionado. Destinatários reais em produção: {{ implode(', ', $destinatariosReais) }}.
@endif

Castilho Soluções Digitais
</x-mail::message>
