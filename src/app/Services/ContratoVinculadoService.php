<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\ContratoVinculado;

class ContratoVinculadoService
{
    /**
     * tenant_id vem sempre do convênio-pai (nunca do usuário autenticado):
     * cobre o caso de um Administrador Interno lançando um contrato em nome
     * de uma prefeitura à qual ele mesmo não pertence.
     */
    public function criar(Convenio $convenio, array $dados): ContratoVinculado
    {
        $contrato = new ContratoVinculado($dados);
        $contrato->convenio_id = $convenio->id;
        $contrato->tenant_id = $convenio->tenant_id;
        $contrato->save();

        return $contrato;
    }

    public function atualizar(ContratoVinculado $contrato, array $dados): ContratoVinculado
    {
        $contrato->fill($dados);
        $contrato->save();

        return $contrato;
    }
}
