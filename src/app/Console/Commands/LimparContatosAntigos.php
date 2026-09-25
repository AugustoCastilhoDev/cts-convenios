<?php

namespace App\Console\Commands;

use App\Models\ContatoComercial;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cumpre o prazo de guarda prometido na Política de Privacidade: pedidos de
 * contato da landing page mais antigos que CONTATO_RETENCAO_MESES são apagados.
 */
#[Signature('contatos:limpar {--meses= : Sobrescreve o prazo de guarda (em meses) só nesta execução}')]
#[Description('Apaga pedidos de contato da landing page mais antigos que o prazo de guarda')]
class LimparContatosAntigos extends Command
{
    public function handle(): int
    {
        $meses = (int) ($this->option('meses') ?? config('contato.retencao_meses'));

        if ($meses < 1) {
            $this->warn('Prazo de guarda desligado (CONTATO_RETENCAO_MESES=0): nada foi apagado.');

            return self::SUCCESS;
        }

        $apagados = ContatoComercial::query()->where('created_at', '<', now()->subMonths($meses))->delete();

        Log::info('Pedidos de contato antigos apagados', ['meses' => $meses, 'apagados' => $apagados]);
        $this->info("{$apagados} pedido(s) com mais de {$meses} meses apagado(s).");

        return self::SUCCESS;
    }
}
