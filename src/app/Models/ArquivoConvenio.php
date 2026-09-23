<?php

namespace App\Models;

use App\Enums\TipoDocumentoConvenio;
use App\Http\Resources\ArquivoConvenioResource;
use App\Models\Concerns\BelongsToTenant;
use App\Policies\ArquivoConvenioPolicy;
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

/**
 * convenio_id, tenant_id e enviado_por ficam fora do #[Fillable] de
 * propósito: são atribuídos pelo ArquivoConvenioService a partir do
 * convênio da rota e do usuário autenticado, nunca do payload do cliente.
 */
#[Table(name: 'arquivos_convenio')]
#[Fillable([
    'tipo_documento',
    'nome_original',
    'mime_type',
    'tamanho_bytes',
    'file_path',
])]
#[UsePolicy(ArquivoConvenioPolicy::class)]
#[UseResource(ArquivoConvenioResource::class)]
class ArquivoConvenio extends Model implements AuditableContract
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumentoConvenio::class,
            'tamanho_bytes' => 'integer',
        ];
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
