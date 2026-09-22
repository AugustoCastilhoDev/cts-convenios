<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConvenioService
{
    /**
     * tenant_id nunca vem direto do payload validado: para Gestor/Fiscal é
     * sempre o próprio tenant do usuário; só o Administrador Interno (sem
     * tenant próprio) pode informar explicitamente para qual prefeitura é.
     */
    public function criar(User $autor, array $dados): Convenio
    {
        $tenantId = $autor->tenant_id ?? ($dados['tenant_id'] ?? null);

        if (! $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Como Administrador Interno, informe o tenant_id da prefeitura ao criar um convênio.',
            ]);
        }

        unset($dados['tenant_id']);

        $convenio = new Convenio($dados);
        $convenio->tenant_id = $tenantId;
        $convenio->save();

        return $convenio;
    }

    public function atualizar(Convenio $convenio, array $dados): Convenio
    {
        $convenio->fill($dados);
        $convenio->save();

        return $convenio;
    }

    public function remover(Convenio $convenio): void
    {
        $convenio->delete();
    }
}
