<?php

namespace App\Http\Requests\ArquivoConvenio;

use App\Enums\TipoDocumentoConvenio;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * convenio_id e tenant_id não entram nas regras: vêm da rota aninhada
 * (/convenios/{convenio}/arquivos) e são atribuídos pelo
 * ArquivoConvenioService. A regra `mimes` valida o tipo real detectado
 * pelo conteúdo do arquivo, não só a extensão informada pelo cliente.
 * O limite de 20 MB (em KB) acompanha upload_max_filesize (php.ini) e
 * client_max_body_size (Nginx).
 */
#[StopOnFirstFailure]
class StoreArquivoConvenioRequest extends FormRequest
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
        return [
            'tipo_documento' => ['required', Rule::enum(TipoDocumentoConvenio::class)],
            'arquivo' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,xml',
                'max:20480',
            ],
        ];
    }
}
