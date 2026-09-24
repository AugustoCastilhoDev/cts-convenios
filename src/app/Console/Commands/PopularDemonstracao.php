<?php

namespace App\Console\Commands;

use App\Enums\StatusConvenio;
use App\Enums\StatusExecucaoContrato;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Carteira fictícia com cara de prefeitura de verdade, para demonstrações e
 * capturas de tela. Todos os dados são inventados. Nunca roda em produção.
 */
#[Signature('demo:popular {--limpar : Remove a carteira de demonstração em vez de criá-la}')]
#[Description('Cria (ou remove com --limpar) convênios fictícios na prefeitura de demonstração')]
class PopularDemonstracao extends Command
{
    /**
     * numero, órgão, objeto, repasse, contrapartida, status, dias até o fim da vigência,
     * dias até a prestação de contas (ou null) e os contratos [empresa, valor, situação].
     *
     * @return array<int, array<string, mixed>>
     */
    private function carteira(): array
    {
        return [
            ['934871/2025', 'Ministério da Saúde', 'Aquisição de ambulância tipo A e equipamentos para a UBS Centro', 380000, 19000, StatusConvenio::EmExecucao, 12, null, [
                ['Veículos Norte Ltda', 372500, StatusExecucaoContrato::EmAndamento],
            ]],
            ['928309/2025', 'Ministério da Saúde', 'Ampliação da Unidade de Pronto Atendimento (UPA)', 1600000, 80000, StatusConvenio::EmExecucao, 75, null, [
                ['Construtora Horizonte S/A', 1510000, StatusExecucaoContrato::EmAndamento],
                ['Instalações Elétricas Aurora', 68000, StatusExecucaoContrato::Paralisado],
            ]],
            ['921450/2025', 'Ministério do Desenvolvimento Regional', 'Pavimentação asfáltica das ruas do bairro Jardim das Flores', 1250000, 62500, StatusConvenio::EmExecucao, 48, null, [
                ['Construtora Vale Verde Ltda', 1180000, StatusExecucaoContrato::EmAndamento],
                ['Engenharia Fiscalizadora ME', 45000, StatusExecucaoContrato::Concluido],
            ]],
            ['940212/2026', 'FNDE', 'Construção de quadra poliesportiva coberta na Escola Municipal Santos Dumont', 780000, 39000, StatusConvenio::Aprovado, 210, null, []],
            ['951034/2026', 'Ministério da Educação', 'Aquisição de ônibus escolar rural', 420000, 21000, StatusConvenio::EmAnalise, 300, null, []],
            ['957660/2026', 'Ministério da Agricultura', 'Patrulha mecanizada: aquisição de trator e implementos agrícolas', 290000, 14500, StatusConvenio::Proposta, 400, null, []],
            ['905118/2024', 'Ministério da Cidadania', 'Reforma do Centro de Referência de Assistência Social (CRAS)', 310000, 15500, StatusConvenio::PrestacaoContas, -20, 25, [
                ['Reformas e Acabamentos Silva ME', 318000, StatusExecucaoContrato::Concluido],
            ]],
            ['899732/2023', 'Ministério do Turismo', 'Revitalização da orla do Rio Verde', 950000, 47500, StatusConvenio::Finalizado, -200, null, [
                ['Paisagismo e Obras Rio Verde', 991000, StatusExecucaoContrato::Concluido],
            ]],
        ];
    }

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Este comando cria dados fictícios e não roda em produção.');

            return self::FAILURE;
        }

        // A prefeitura de demonstração criada pelo seeder.
        $tenant = Tenant::query()->where('razao_social', 'like', '%Exemplópolis%')->first();

        if (! $tenant) {
            $this->error('Prefeitura de demonstração não encontrada. Rode `php artisan db:seed` antes.');

            return self::FAILURE;
        }

        $numeros = array_column($this->carteira(), 0);

        // Sempre recomeça do zero: rodar duas vezes não duplica.
        $removidos = $this->remover($tenant, $numeros);

        if ($this->option('limpar')) {
            $this->info("Carteira de demonstração removida ({$removidos} convênio(s)).");

            return self::SUCCESS;
        }

        foreach ($this->carteira() as [$numero, $orgao, $objeto, $repasse, $contrapartida, $status, $diasVigencia, $diasPrestacao, $contratos]) {
            $convenio = new Convenio([
                'numero_convenio' => $numero,
                'orgao_concedente' => $orgao,
                'objeto' => $objeto,
                'valor_repasse' => $repasse,
                'valor_contrapartida' => $contrapartida,
                'status' => $status,
                'data_assinatura' => today()->addDays($diasVigencia)->subYear(),
                'data_vigencia_fim' => today()->addDays($diasVigencia),
                'prazo_prestacao_contas' => $diasPrestacao === null ? null : today()->addDays($diasPrestacao),
            ]);
            $convenio->forceFill(['tenant_id' => $tenant->id])->save();

            foreach ($contratos as $indice => [$empresa, $valor, $situacao]) {
                $contrato = new ContratoVinculado([
                    'convenio_id' => $convenio->id,
                    'numero_contrato' => sprintf('%03d/%d', $indice + 1, today()->year),
                    'empresa_contratada' => $empresa,
                    'valor_contratado' => $valor,
                    'status_execucao' => $situacao,
                ]);
                $contrato->forceFill(['tenant_id' => $tenant->id, 'convenio_id' => $convenio->id])->save();
            }
        }

        $this->info(count($numeros).' convênios fictícios criados em "'.$tenant->razao_social.'". Remova com `demo:popular --limpar`.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $numeros
     */
    private function remover(Tenant $tenant, array $numeros): int
    {
        $convenios = Convenio::withTrashed()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('numero_convenio', $numeros)
            ->get();

        foreach ($convenios as $convenio) {
            ContratoVinculado::withTrashed()->withoutGlobalScopes()->where('convenio_id', $convenio->id)->forceDelete();
            $convenio->forceDelete();
        }

        return $convenios->count();
    }
}
