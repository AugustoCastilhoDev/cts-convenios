<?php

namespace App\Services;

use App\Models\ContatoComercial;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operações da equipe sobre os pedidos de contato da landing page. Os textos
 * vêm de visitantes anônimos: no CSV nada é confiável.
 */
class PedidoContatoService
{
    private const CABECALHO_CSV = ['Recebido em', 'Nome', 'Cargo', 'Município', 'E-mail', 'Telefone', 'Mensagem', 'Aceite em', 'Situação', 'Respondido em'];

    public function marcarRespondido(ContatoComercial $contato, bool $respondido): ContatoComercial
    {
        $contato->respondido_em = $respondido ? now() : null;
        $contato->save();

        return $contato;
    }

    /** Exclusão definitiva (pedido do titular, LGPD): não há lixeira; o registro fica só no log. */
    public function excluir(ContatoComercial $contato, User $autor): void
    {
        $contato->delete();

        Log::info('Pedido de contato excluído', ['contato_id' => $contato->id, 'user_id' => $autor->id]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function exportarCsv(User $autor, array $filtros): StreamedResponse
    {
        $pedidos = ContatoComercial::query()->filtrar($filtros)->latest()->get();

        Log::info('Pedidos de contato exportados', ['user_id' => $autor->id, 'registros' => $pedidos->count(), 'filtros' => $filtros]);

        return response()->streamDownload(function () use ($pedidos) {
            $saida = fopen('php://output', 'w');

            // BOM para o Excel reconhecer UTF-8; ";" é o separador padrão do Excel em pt-BR.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, self::CABECALHO_CSV, ';');

            foreach ($pedidos as $pedido) {
                fputcsv($saida, [
                    $pedido->created_at->format('d/m/Y H:i'),
                    $this->textoSeguro($pedido->nome),
                    $this->textoSeguro($pedido->cargo),
                    $this->textoSeguro($pedido->municipio),
                    $this->textoSeguro($pedido->email),
                    $this->textoSeguro($pedido->telefone),
                    $this->textoSeguro($pedido->mensagem),
                    $pedido->aceite_em?->format('d/m/Y H:i'),
                    $pedido->respondido_em ? 'Respondido' : 'Pendente',
                    $pedido->respondido_em?->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($saida);
        }, 'pedidos-de-contato-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Impede "injeção de fórmula": um texto começando com = + - @ seria
     * executado como fórmula ao abrir o CSV no Excel.
     */
    private function textoSeguro(?string $texto): string
    {
        $texto = (string) $texto;

        return preg_match('/^[=+\-@\t\r]/', $texto) ? "'".$texto : $texto;
    }
}
