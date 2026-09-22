<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Isolamento multi-tenant (Single Database, Multi-tenant): restringe toda
 * consulta às linhas pertencentes ao tenant_id do usuário autenticado.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($tenantId = Auth::user()?->tenant_id) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
