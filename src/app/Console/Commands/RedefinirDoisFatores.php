<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DoisFatoresService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Caminho de emergência do super administrador que perdeu o celular e os códigos de recuperação: quem
 * tem acesso ao servidor (shell) redefine o 2FA da conta. É o único jeito, de propósito: nenhuma tela
 * ou API deixa um administrador tirar o 2FA do super administrador. No próximo login ele ativa de novo.
 * O Auditing ignora o console, então o comando registra a ação no log da aplicação.
 */
#[Signature('admin:redefinir-2fa {email : E-mail da conta}')]
#[Description('Apaga o 2FA de uma conta (perdeu o celular e os códigos de recuperação) e derruba as sessões dela')]
class RedefinirDoisFatores extends Command
{
    public function handle(DoisFatoresService $doisFatores): int
    {
        $usuario = User::query()->where('email', $this->argument('email'))->first();

        if (! $usuario) {
            $this->error('Nenhuma conta com esse e-mail.');

            return self::FAILURE;
        }

        if (! $this->confirm("Apagar o 2FA de {$usuario->email} ({$usuario->role->label()})? A pessoa será desconectada.")) {
            return self::FAILURE;
        }

        $doisFatores->redefinir($usuario);

        $this->info("2FA de {$usuario->email} apagado. No próximo acesso a pessoa ativa de novo.");

        return self::SUCCESS;
    }
}
