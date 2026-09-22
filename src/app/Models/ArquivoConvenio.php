<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Table(name: 'arquivos_convenio')]
#[Fillable([
    'convenio_id',
    'tipo_documento',
    'file_path',
])]
class ArquivoConvenio extends Model implements AuditableContract
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }
}
