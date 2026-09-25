{{-- Trecho que depende de uma decisão ou de um dado ainda não informado: aparece destacado. --}}
<mark class="rounded bg-amber-200 px-1 text-amber-950">{{ $slot->isEmpty() ? 'a preencher' : $slot }}</mark>
