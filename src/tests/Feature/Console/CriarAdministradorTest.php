<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class CriarAdministradorTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    public function test_cria_administrador_interno_sem_tenant(): void
    {
        $this->artisan('admin:criar', ['email' => 'nova.equipe@exemplo.com.br', '--nome' => 'Equipe Nova'])
            ->expectsQuestion('Senha', 'SenhaForte123')
            ->expectsQuestion('Confirme a senha', 'SenhaForte123')
            ->expectsOutputToContain('Administrador Interno criado')
            ->assertSuccessful();

        $admin = User::where('email', 'nova.equipe@exemplo.com.br')->firstOrFail();
        $this->assertSame(UserRole::AdministradorInterno, $admin->role);
        $this->assertNull($admin->tenant_id);
        $this->assertTrue($admin->active);
        $this->assertTrue(password_verify('SenhaForte123', $admin->password));
    }

    public function test_falha_quando_as_senhas_nao_conferem(): void
    {
        $this->artisan('admin:criar', ['email' => 'x@exemplo.com.br'])
            ->expectsQuestion('Senha', 'SenhaForte123')
            ->expectsQuestion('Confirme a senha', 'Diferente123')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'x@exemplo.com.br']);
    }

    public function test_falha_com_senha_fraca_ou_email_ja_existente(): void
    {
        $this->artisan('admin:criar', ['email' => 'fraca@exemplo.com.br'])
            ->expectsQuestion('Senha', 'curta')
            ->expectsQuestion('Confirme a senha', 'curta')
            ->assertFailed();

        $existente = $this->criarAdmin();

        $this->artisan('admin:criar', ['email' => $existente->email])
            ->expectsQuestion('Senha', 'SenhaForte123')
            ->expectsQuestion('Confirme a senha', 'SenhaForte123')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'fraca@exemplo.com.br']);
    }
}
