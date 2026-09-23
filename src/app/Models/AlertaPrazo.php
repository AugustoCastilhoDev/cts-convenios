<?php

namespace App\Models;

use App\Enums\TipoPrazo;
use App\Http\Resources\AlertaPrazoResource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de cada alerta de prazo gerado pelo Motor de Alertas (histórico
 * consultável e trava de idempotência). Nada é preenchível em massa: só o
 * AlertaPrazoService cria e atualiza estas linhas.
 */
#[Table(name: 'alertas_prazo')]
#[UseResource(AlertaPrazoResource::class)]
class AlertaPrazo extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected function casts(): array
    {
        return [
            'tipo_prazo' => TipoPrazo::class,
            'marco_dias' => 'integer',
            'data_prazo' => 'date',
            'enviado_em' => 'datetime',
            'cancelado_em' => 'datetime',
            'destinatarios' => 'integer',
        ];
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }
}
