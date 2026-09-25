<?php

namespace App\Models;

use App\Policies\ContatoComercialPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pedido de contato vindo da landing page. `aceite_em` e `ip` são definidos
 * pelo ContatoComercialService, nunca pelo payload. `respondido_em` é o
 * controle da equipe (tela do administrador).
 */
#[Table(name: 'contatos_comerciais')]
#[Fillable(['nome', 'cargo', 'municipio', 'email', 'telefone', 'mensagem'])]
#[UsePolicy(ContatoComercialPolicy::class)]
class ContatoComercial extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'aceite_em' => 'datetime',
            'respondido_em' => 'datetime',
        ];
    }

    /**
     * Filtros da tela do administrador (e da exportação, para o arquivo trazer
     * exatamente o que a tela mostra).
     *
     * @param  array{busca?: ?string, situacao?: ?string, de?: ?string, ate?: ?string}  $filtros
     */
    public function scopeFiltrar(Builder $query, array $filtros): void
    {
        $query
            ->when($filtros['busca'] ?? null, function (Builder $query, string $busca) {
                $termo = '%'.mb_strtolower($busca).'%';

                $query->where(fn (Builder $query) => $query
                    ->whereRaw('lower(nome) like ?', [$termo])
                    ->orWhereRaw('lower(municipio) like ?', [$termo])
                    ->orWhereRaw('lower(email) like ?', [$termo]));
            })
            ->when(($filtros['situacao'] ?? null) === 'pendente', fn (Builder $query) => $query->whereNull('respondido_em'))
            ->when(($filtros['situacao'] ?? null) === 'respondido', fn (Builder $query) => $query->whereNotNull('respondido_em'))
            ->when($filtros['de'] ?? null, fn (Builder $query, string $data) => $query->where('created_at', '>=', $data.' 00:00:00'))
            ->when($filtros['ate'] ?? null, fn (Builder $query, string $data) => $query->where('created_at', '<=', $data.' 23:59:59'));
    }
}
