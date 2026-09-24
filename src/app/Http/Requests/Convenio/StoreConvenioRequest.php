<?php

namespace App\Http\Requests\Convenio;

use App\Enums\Secretaria;
use App\Enums\StatusConvenio;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Quem pode criar" é decidido pela ConvenioPolicy (via #[Authorize] no
 * ConvenioController). authorize() aqui só garante que a rota exige um
 * usuário autenticado — a forma do payload é responsabilidade desta classe.
 */
#[StopOnFirstFailure]
class StoreConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Gestor/Fiscal só criam no próprio tenant; Administrador Interno
        // (tenant_id nulo) precisa informar para qual prefeitura é o convênio.
        $tenantId = $this->user()->tenant_id ?? $this->input('tenant_id');

        return [
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'numero_convenio' => [
                'required', 'string', 'max:255',
                Rule::unique('convenios', 'numero_convenio')->where('tenant_id', $tenantId),
            ],
            'orgao_concedente' => ['required', 'string', 'max:255'],
            'objeto' => ['required', 'string'],
            // Opcional na API (convênios antigos e integrações não têm classificação); a tela de
            // criação exige a escolha. Ver ROADMAP: virar obrigatória quando tudo estiver classificado.
            'secretaria' => ['nullable', Rule::enum(Secretaria::class)],
            'valor_repasse' => ['required', 'numeric', 'min:0'],
            'valor_contrapartida' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(StatusConvenio::class)],
            'data_assinatura' => ['nullable', 'date'],
            'data_vigencia_fim' => ['nullable', 'date', 'after_or_equal:data_assinatura'],
            'prazo_prestacao_contas' => ['nullable', 'date'],
        ];
    }
}
