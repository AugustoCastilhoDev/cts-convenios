<?php

namespace App\Models;

use App\Enums\StatusExecucaoContrato;
use App\Http\Resources\ContratoVinculadoResource;
use App\Models\Concerns\BelongsToTenant;
use App\Policies\ContratoVinculadoPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Table(name: 'contratos_vinculados')]
#[Fillable([
    'convenio_id',
    'numero_contrato',
    'empresa_contratada',
    'valor_contratado',
    'status_execucao',
])]
#[UsePolicy(ContratoVinculadoPolicy::class)]
#[UseResource(ContratoVinculadoResource::class)]
class ContratoVinculado extends Model implements AuditableContract
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'valor_contratado' => 'decimal:2',
            'status_execucao' => StatusExecucaoContrato::class,
        ];
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }
}
