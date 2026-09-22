<?php

namespace Tests\Unit\Models;

use App\Enums\StatusConvenio;
use App\Enums\StatusExecucaoContrato;
use App\Models\Convenio;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvenioTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_disponivel_desconta_o_total_contratado(): void
    {
        $tenant = Tenant::create(['razao_social' => 'Prefeitura de Teste', 'cnpj' => '00.000.000/0001-00']);

        $convenio = Convenio::forceCreate([
            'tenant_id' => $tenant->id,
            'numero_convenio' => '1/2026',
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Teste',
            'valor_repasse' => 500000,
            'valor_contrapartida' => 50000,
            'status' => StatusConvenio::EmExecucao,
        ]);

        // tenant_id não é #[Fillable]; create() salvaria antes de conseguirmos
        // setá-lo, então montamos com make() e atribuímos antes do save().
        $contrato = $convenio->contratosVinculados()->make([
            'numero_contrato' => '1/2026',
            'empresa_contratada' => 'Empresa Teste',
            'valor_contratado' => 300000,
            'status_execucao' => StatusExecucaoContrato::EmAndamento,
        ]);
        $contrato->tenant_id = $tenant->id;
        $contrato->save();

        $this->assertEquals(250000, $convenio->fresh()->saldo_disponivel);
    }

    public function test_dias_para_vencimento_e_positivo_para_data_futura(): void
    {
        $tenant = Tenant::create(['razao_social' => 'Prefeitura de Teste', 'cnpj' => '00.000.000/0001-00']);

        $convenio = Convenio::forceCreate([
            'tenant_id' => $tenant->id,
            'numero_convenio' => '1/2026',
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Teste',
            'valor_repasse' => 100,
            'valor_contrapartida' => 0,
            'status' => StatusConvenio::EmExecucao,
            'data_vigencia_fim' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertEquals(30, $convenio->dias_para_vencimento);
    }

    public function test_dias_para_vencimento_e_negativo_para_convenio_vencido(): void
    {
        $tenant = Tenant::create(['razao_social' => 'Prefeitura de Teste', 'cnpj' => '00.000.000/0001-00']);

        $convenio = Convenio::forceCreate([
            'tenant_id' => $tenant->id,
            'numero_convenio' => '1/2026',
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Teste',
            'valor_repasse' => 100,
            'valor_contrapartida' => 0,
            'status' => StatusConvenio::Finalizado,
            'data_vigencia_fim' => now()->subDays(10)->toDateString(),
        ]);

        $this->assertEquals(-10, $convenio->dias_para_vencimento);
    }
}
