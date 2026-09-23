<?php

namespace App\Models;

use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Table(name: 'tenants')]
#[Fillable(['razao_social', 'cnpj', 'active'])]
#[UsePolicy(TenantPolicy::class)]
class Tenant extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;
    use HasUuids;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class);
    }
}
