<?php

namespace App\Models;

use App\Enums\Secretaria;
use App\Enums\StatusConvenio;
use App\Http\Resources\ConvenioResource;
use App\Models\Concerns\BelongsToTenant;
use App\Policies\ConvenioPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Table(name: 'convenios')]
#[Fillable([
    'numero_convenio',
    'orgao_concedente',
    'objeto',
    'secretaria',
    'valor_repasse',
    'valor_contrapartida',
    'status',
    'data_assinatura',
    'data_vigencia_fim',
    'prazo_prestacao_contas',
])]
#[UsePolicy(ConvenioPolicy::class)]
#[UseResource(ConvenioResource::class)]
class Convenio extends Model implements AuditableContract
{
    /** Valor de filtro para "convênios sem secretaria" (não é um valor gravado no banco). */
    public const SEM_SECRETARIA = 'sem_secretaria';

    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'valor_repasse' => 'decimal:2',
            'valor_contrapartida' => 'decimal:2',
            'status' => StatusConvenio::class,
            'secretaria' => Secretaria::class,
            'data_assinatura' => 'date',
            'data_vigencia_fim' => 'date',
            'prazo_prestacao_contas' => 'date',
        ];
    }

    /**
     * Filtros compartilhados pela listagem e pela exportação, para que o
     * relatório traga exatamente o que a tela mostra.
     */
    public function scopeFiltrar(Builder $query, ?string $status, ?string $busca): void
    {
        $query
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($busca, fn (Builder $query) => $query->where('numero_convenio', 'like', '%'.$busca.'%'));
    }

    /**
     * Restringe a uma secretaria. Sem valor não filtra; o valor especial
     * "sem_secretaria" traz os convênios ainda não classificados.
     */
    public function scopeDaSecretaria(Builder $query, ?string $secretaria): void
    {
        $query->when($secretaria, fn (Builder $query) => $secretaria === self::SEM_SECRETARIA
            ? $query->whereNull('secretaria')
            : $query->where('secretaria', $secretaria));
    }

    public function contratosVinculados(): HasMany
    {
        return $this->hasMany(ContratoVinculado::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaPrazo::class);
    }

    public function arquivos(): HasMany
    {
        return $this->hasMany(ArquivoConvenio::class);
    }

    /**
     * Saldo Disponível = (Valor Repasse + Contrapartida) - Total Contratado.
     *
     * Acessor de conveniência para uso pontual (ex.: tela de detalhe). Para
     * listagens/dashboards, prefira `withSum('contratosVinculados', 'valor_contratado')`
     * no Service Layer para evitar N+1.
     */
    protected function saldoDisponivel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->valor_repasse + $this->valor_contrapartida
                - $this->contratosVinculados()->sum('valor_contratado'),
        );
    }

    /**
     * Dias restantes até o fim da vigência (negativo se já vencido).
     * Base para a régua de alertas de 90/60/30/15 dias (Módulo 3).
     */
    protected function diasParaVencimento(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->data_vigencia_fim
                ? now()->startOfDay()->diffInDays($this->data_vigencia_fim, false)
                : null,
        );
    }
}
