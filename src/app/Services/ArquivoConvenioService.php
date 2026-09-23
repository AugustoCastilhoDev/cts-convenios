<?php

namespace App\Services;

use App\Models\ArquivoConvenio;
use App\Models\Convenio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArquivoConvenioService
{
    /**
     * Disco privado (storage/app/private): nada é servido por URL pública,
     * todo acesso passa pelo endpoint de download e sua Policy.
     */
    private const DISCO = 'local';

    /**
     * tenant_id/convenio_id vêm sempre do convênio-pai. O arquivo é gravado
     * sob um nome gerado (UUID) dentro de um diretório por tenant/convênio —
     * o nome enviado pelo cliente nunca vira caminho em disco, só metadado.
     */
    public function armazenar(Convenio $convenio, User $autor, UploadedFile $arquivo, string $tipoDocumento): ArquivoConvenio
    {
        $caminho = $arquivo->store("arquivos-convenio/{$convenio->tenant_id}/{$convenio->id}", self::DISCO);

        $registro = new ArquivoConvenio([
            'tipo_documento' => $tipoDocumento,
            'nome_original' => $arquivo->getClientOriginalName(),
            'mime_type' => $arquivo->getMimeType(),
            'tamanho_bytes' => $arquivo->getSize(),
            'file_path' => $caminho,
        ]);
        $registro->convenio_id = $convenio->id;
        $registro->tenant_id = $convenio->tenant_id;
        $registro->enviado_por = $autor->id;
        $registro->save();

        return $registro;
    }

    public function baixar(ArquivoConvenio $arquivo): StreamedResponse
    {
        return Storage::disk(self::DISCO)->download($arquivo->file_path, $arquivo->nome_original);
    }

    /**
     * Soft delete: o registro e o arquivo físico são preservados para fins
     * de auditoria (TCE) — só some das listagens.
     */
    public function remover(ArquivoConvenio $arquivo): void
    {
        $arquivo->delete();
    }
}
