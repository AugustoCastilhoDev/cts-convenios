@props(['campo'])
@php($valor = config("empresa.$campo"))
@if (filled($valor)){{ $valor }}@else<x-pendente>{{ str_replace('_', ' ', $campo) }}: a preencher</x-pendente>@endif
