<?php

namespace Tests\Feature\Auth;

use App\Notifications\RedefinirSenha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class RedefinicaoSenhaTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private const SENHA_NOVA = 'NovaSenhaForte123';

    /** Corpo de um pedido de redefinição com um token de verdade, como o do e-mail. */
    private function corpoDeRedefinicao($usuario, array $sobrescrever = []): array
    {
        return array_merge([
            'token' => Password::broker()->createToken($usuario),
            'email' => $usuario->email,
            'password' => self::SENHA_NOVA,
            'password_confirmation' => self::SENHA_NOVA,
        ], $sobrescrever);
    }

    // ---------------------------------------------------------------- pedido do link

    public function test_usuario_ativo_recebe_o_link_por_email(): void
    {
        Notification::fake();
        $gestor = $this->criarGestor();

        $this->postJson('/api/esqueci-senha', ['email' => $gestor->email])
            ->assertOk()
            ->assertJsonPath('message', fn ($mensagem) => str_contains($mensagem, 'Se este e-mail estiver cadastrado'));

        Notification::assertSentTo($gestor, RedefinirSenha::class);
    }

    public function test_o_email_leva_ao_sistema_com_o_token_e_o_endereco_do_usuario(): void
    {
        $gestor = $this->criarGestor();

        $mensagem = (new RedefinirSenha('token-abc'))->toMail($gestor);

        $this->assertSame('Redefinição de senha — CTS Convênios', $mensagem->subject);
        $this->assertStringContainsString('/app/redefinir-senha?token=token-abc&email='.urlencode($gestor->email), $mensagem->actionUrl);
        $this->assertSame('Criar nova senha', $mensagem->actionText);
    }

    public function test_em_teste_o_email_e_redirecionado_e_nunca_vai_para_a_pessoa(): void
    {
        config(['alertas.redirecionar_para' => 'equipe@castilho.test']);
        $gestor = $this->criarGestor();

        $this->assertSame('equipe@castilho.test', $gestor->routeNotificationForMail());

        $mensagem = (new RedefinirSenha('tok'))->toMail($gestor);
        $this->assertStringStartsWith('[TESTE] ', $mensagem->subject);
        $this->assertContains("Modo de teste: este e-mail foi redirecionado. Destinatário real em produção: {$gestor->email}.", $mensagem->outroLines);
    }

    public function test_em_producao_o_email_vai_para_o_endereco_da_pessoa(): void
    {
        config(['alertas.redirecionar_para' => null]);
        $gestor = $this->criarGestor();

        $this->assertSame($gestor->email, $gestor->routeNotificationForMail());
        $this->assertStringNotContainsString('[TESTE]', (new RedefinirSenha('tok'))->toMail($gestor)->subject);
    }

    public function test_o_link_do_email_usa_o_endereco_oficial_mesmo_com_host_forjado(): void
    {
        config(['app.url' => 'https://cts.exemplo.com.br']);
        Notification::fake();
        $gestor = $this->criarGestor();

        // Ataque de "envenenamento de link": o pedido chega com o Host do atacante.
        $this->withHeader('Host', 'atacante.example')->postJson('/api/esqueci-senha', ['email' => $gestor->email])->assertOk();

        Notification::assertSentTo($gestor, RedefinirSenha::class, function (RedefinirSenha $notificacao) use ($gestor) {
            $link = $notificacao->toMail($gestor)->actionUrl;

            return str_starts_with($link, 'https://cts.exemplo.com.br/app/redefinir-senha?') && ! str_contains($link, 'atacante');
        });
    }

    public function test_a_resposta_e_a_mesma_para_email_inexistente_e_ninguem_recebe_nada(): void
    {
        Notification::fake();
        $gestor = $this->criarGestor();

        $existente = $this->postJson('/api/esqueci-senha', ['email' => $gestor->email]);
        $inexistente = $this->postJson('/api/esqueci-senha', ['email' => 'ninguem@exemplo.gov.br']);

        $this->assertSame($existente->status(), $inexistente->status());
        $this->assertSame($existente->json(), $inexistente->json());
        Notification::assertSentToTimes($gestor, RedefinirSenha::class, 1);
        Notification::assertCount(1);
    }

    public function test_conta_desativada_nao_recebe_link(): void
    {
        Notification::fake();
        $inativo = $this->criarGestor();
        $inativo->forceFill(['active' => false])->save();

        $tenantInativo = $this->criarTenant(['active' => false]);
        $daPrefeituraInativa = $this->criarGestor($tenantInativo);

        $this->postJson('/api/esqueci-senha', ['email' => $inativo->email])->assertOk();
        $this->postJson('/api/esqueci-senha', ['email' => $daPrefeituraInativa->email])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_pedido_exige_um_email_valido(): void
    {
        $this->postJson('/api/esqueci-senha', [])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/esqueci-senha', ['email' => 'isto-nao-e-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_limita_pedidos_por_minuto_no_mesmo_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/esqueci-senha', ['email' => "pessoa{$i}@exemplo.gov.br"])->assertOk();
        }

        $this->postJson('/api/esqueci-senha', ['email' => 'outra@exemplo.gov.br'])->assertStatus(429);
    }

    public function test_limita_pedidos_por_hora_para_o_mesmo_email(): void
    {
        Notification::fake();
        $gestor = $this->criarGestor();

        // O limite por IP (3 por minuto) é maior que o tempo do teste: troca-se o IP a cada rodada.
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/esqueci-senha', ['email' => $gestor->email])->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/esqueci-senha', ['email' => $gestor->email])->assertStatus(429);
    }

    // ---------------------------------------------------------------- uso do link

    public function test_redefine_a_senha_derruba_todas_as_sessoes_e_libera_o_acesso(): void
    {
        $gestor = $this->criarGestor();
        $gestor->forceFill(['must_change_password' => true])->save();
        $gestor->createToken('celular');
        $gestor->createToken('notebook');

        $this->postJson('/api/redefinir-senha', $this->corpoDeRedefinicao($gestor))
            ->assertOk()
            ->assertJsonPath('message', 'Senha alterada. Entre com a nova senha.');

        $gestor->refresh();
        $this->assertTrue(password_verify(self::SENHA_NOVA, $gestor->password));
        $this->assertFalse($gestor->must_change_password);
        $this->assertSame(0, $gestor->tokens()->count());

        $this->postJson('/api/login', ['email' => $gestor->email, 'password' => self::SENHA_NOVA, 'device_name' => 'x'])->assertOk();
    }

    public function test_o_link_so_funciona_uma_vez(): void
    {
        $gestor = $this->criarGestor();
        $corpo = $this->corpoDeRedefinicao($gestor);

        $this->postJson('/api/redefinir-senha', $corpo)->assertOk();

        $this->postJson('/api/redefinir-senha', array_merge($corpo, ['password' => 'OutraSenhaForte456', 'password_confirmation' => 'OutraSenhaForte456']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->assertTrue(password_verify(self::SENHA_NOVA, $gestor->fresh()->password));
    }

    public function test_o_link_vence_em_60_minutos(): void
    {
        $gestor = $this->criarGestor();
        $corpo = $this->corpoDeRedefinicao($gestor);

        $this->travel(61)->minutes();

        $this->postJson('/api/redefinir-senha', $corpo)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertTrue(password_verify('password', $gestor->fresh()->password));
    }

    public function test_token_errado_ou_email_de_outra_pessoa_dao_a_mesma_resposta(): void
    {
        $gestor = $this->criarGestor();
        $outro = $this->criarFiscal($gestor->tenant);

        $tokenErrado = $this->postJson('/api/redefinir-senha', $this->corpoDeRedefinicao($gestor, ['token' => 'token-que-nao-existe']));
        $emailDeOutro = $this->postJson('/api/redefinir-senha', $this->corpoDeRedefinicao($gestor, ['email' => $outro->email]));
        $emailInexistente = $this->postJson('/api/redefinir-senha', $this->corpoDeRedefinicao($gestor, ['email' => 'ninguem@exemplo.gov.br']));

        foreach ([$tokenErrado, $emailDeOutro, $emailInexistente] as $resposta) {
            $resposta->assertUnprocessable()->assertJsonValidationErrors('token');
            $this->assertSame($tokenErrado->json(), $resposta->json());
        }

        $this->assertTrue(password_verify('password', $gestor->fresh()->password));
        $this->assertTrue(password_verify('password', $outro->fresh()->password));
    }

    public function test_recusa_senha_fraca_e_confirmacao_diferente(): void
    {
        $gestor = $this->criarGestor();
        $token = Password::broker()->createToken($gestor);
        $base = ['token' => $token, 'email' => $gestor->email];

        $this->postJson('/api/redefinir-senha', $base + ['password' => 'curta1', 'password_confirmation' => 'curta1'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/redefinir-senha', $base + ['password' => 'SoLetrasNaSenha', 'password_confirmation' => 'SoLetrasNaSenha'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/redefinir-senha', $base + ['password' => self::SENHA_NOVA, 'password_confirmation' => 'diferente12345'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        // Nenhuma tentativa inválida gasta o link: ele ainda funciona com uma senha boa.
        $this->postJson('/api/redefinir-senha', $base + ['password' => self::SENHA_NOVA, 'password_confirmation' => self::SENHA_NOVA])->assertOk();
    }

    public function test_conta_desativada_depois_do_pedido_nao_consegue_redefinir(): void
    {
        $gestor = $this->criarGestor();
        $corpo = $this->corpoDeRedefinicao($gestor);
        $gestor->forceFill(['active' => false])->save();

        $this->postJson('/api/redefinir-senha', $corpo)->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->assertTrue(password_verify('password', $gestor->fresh()->password));
    }

    public function test_limita_tentativas_de_uso_do_link_por_ip(): void
    {
        $gestor = $this->criarGestor();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/redefinir-senha', $this->corpoDeRedefinicao($gestor, ['token' => "chute-{$i}"]))->assertUnprocessable();
        }

        $this->postJson('/api/redefinir-senha', $this->corpoDeRedefinicao($gestor))->assertStatus(429);
    }
}
