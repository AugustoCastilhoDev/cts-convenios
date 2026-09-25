<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Único caminho para criar um Administrador Interno (cross-tenant): a senha
 * é digitada de forma oculta, nunca passada como argumento (evita vazar no
 * histórico do shell), e nada fica fixo no código como no DatabaseSeeder.
 * Como o Auditing ignora o console (audit.console = false), a criação de
 * administradores não aparece na trilha de auditoria — registre por fora.
 */
#[Signature('admin:criar {email : E-mail de acesso} {--nome=Administrador : Nome do usuário}')]
#[Description('Cria um Administrador Interno (equipe da plataforma, acesso a todas as prefeituras)')]
class CriarAdministrador extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $senha = $this->secret('Senha');

        if ($senha !== $this->secret('Confirme a senha')) {
            $this->error('As senhas não conferem.');

            return self::FAILURE;
        }

        $validador = Validator::make(
            ['email' => $email, 'password' => $senha],
            [
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $mensagem) {
                $this->error($mensagem);
            }

            return self::FAILURE;
        }

        $administrador = new User([
            'name' => $this->option('nome'),
            'email' => $email,
            'password' => $senha,
        ]);
        $administrador->forceFill([
            'tenant_id' => null,
            'role' => UserRole::AdministradorInterno,
            'active' => true,
        ])->save();

        $this->info("Administrador Interno criado: {$email}");

        return self::SUCCESS;
    }
}
