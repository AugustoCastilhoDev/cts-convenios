<x-mail::message>
# Novo pedido de contato

Alguém preencheu o formulário da página inicial do CTS Convênios.

<x-mail::table>
| | |
|:--|:--|
| **Nome** | {{ $contato->nome }} |
| **Cargo** | {{ $contato->cargo ?: '—' }} |
| **Município** | {{ $contato->municipio }} |
| **E-mail** | {{ $contato->email }} |
| **Telefone** | {{ $contato->telefone ?: '—' }} |
</x-mail::table>

@if ($contato->mensagem)
**Mensagem**

{{ $contato->mensagem }}
@endif

Responder a este e-mail escreve direto para {{ $contato->nome }}.

Recebido em {{ $contato->created_at->format('d/m/Y H:i') }}.
</x-mail::message>
