<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\DoisFatoresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class DoisFatoresTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private const PADRAO_CODIGO_DE_RECUPERACAO = '/^[2-9A-HJ-NP-Z]{5}-[2-9A-HJ-NP-Z]{5}$/';

    /** Depois de um actingAs o guard padrão é "sanctum" e o login usa o "web": limpa os dois lados. */
    private function semSessao(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    private function entrar(string $email, string $senha = 'password'): TestResponse
    {
        $this->semSessao();
        $resposta = $this->postJson('/api/login', ['email' => $email, 'password' => $senha, 'device_name' => 'teste']);
        $this->semSessao();

        return $resposta;
    }

    private function codigoAtual(string $segredo): string
    {
        return app(Google2FA::class)->getCurrentOtp($segredo);
    }

    /** Código de um período vizinho (-1, +1): dentro da tolerância de um período para cada lado. */
    private function codigoVizinho(string $segredo, int $deslocamento): string
    {
        $google2fa = app(Google2FA::class);

        return $google2fa->oathTotp($segredo, $google2fa->getTimestamp() + $deslocamento);
    }

    /**
     * Deixa o usuário com o 2FA ativo, como se ele tivesse feito a ativação pela tela.
     *
     * @return array{segredo: string, codigos: array<int, string>}
     */
    private function ativarDoisFatores(User $usuario): array
    {
        $servico = app(DoisFatoresService::class);
        $segredo = $servico->iniciar($usuario)['segredo'];
        $codigos = $servico->confirmar($usuario, $this->codigoAtual($segredo));

        // Cada teste começa sem "último período usado": os que testam a repetição gravam o seu.
        $usuario->forceFill(['two_factor_ultimo_passo' => null])->save();

        return ['segredo' => $segredo, 'codigos' => $codigos];
    }

    private function comoUsuario(User $usuario): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($usuario);
    }

    public function test_ativacao_pede_a_senha_devolve_o_segredo_e_so_vale_depois_de_confirmada(): void
    {
        $gestor = $this->criarGestor();
        $this->comoUsuario($gestor);

        $this->postJson('/api/2fa/iniciar')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/2fa/iniciar', ['password' => 'errada-mesmo'])->assertUnprocessable()->assertJsonValidationErrors('password');

        $resposta = $this->postJson('/api/2fa/iniciar', ['password' => 'password'])
            ->assertOk();
        $this->assertStringContainsString('no-store', $resposta->headers->get('Cache-Control'));
        $segredo = $resposta->json('data.segredo');

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $segredo);
        $this->assertStringStartsWith('otpauth://totp/', $resposta->json('data.url'));
        $this->assertStringContainsString("secret={$segredo}", $resposta->json('data.url'));
        $this->assertStringContainsString(rawurlencode($gestor->email), $resposta->json('data.url'));

        // Só o segredo, sem a confirmação: não é 2FA ainda, e o login segue igual.
        $this->assertFalse($gestor->fresh()->doisFatoresAtivo());
        $this->entrar($gestor->email)->assertOk()->assertJsonMissingPath('dois_fatores')->assertJsonStructure(['token']);
    }

    public function test_o_segredo_e_os_codigos_ficam_criptografados_no_banco(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo, 'codigos' => $codigos] = $this->ativarDoisFatores($gestor);

        $linha = DB::table('users')->where('id', $gestor->id)->first();

        $this->assertNotSame($segredo, $linha->two_factor_secret);
        $this->assertStringNotContainsString($segredo, $linha->two_factor_secret);
        foreach ($codigos as $codigo) {
            $this->assertStringNotContainsString($codigo, $linha->two_factor_recovery_codes);
            $this->assertStringNotContainsString($codigo, json_encode($gestor->fresh()->two_factor_recovery_codes));
        }
        $this->assertSame($segredo, $gestor->fresh()->two_factor_secret);
    }

    public function test_confirmar_com_codigo_errado_falha_e_com_o_certo_ativa_e_entrega_os_codigos_de_recuperacao(): void
    {
        $gestor = $this->criarGestor();
        $this->comoUsuario($gestor);
        $segredo = $this->postJson('/api/2fa/iniciar', ['password' => 'password'])->json('data.segredo');

        $this->postJson('/api/2fa/confirmar', ['codigo' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->assertFalse($gestor->fresh()->doisFatoresAtivo());

        $resposta = $this->postJson('/api/2fa/confirmar', ['codigo' => $this->codigoAtual($segredo)])
            ->assertOk();
        $this->assertStringContainsString('no-store', $resposta->headers->get('Cache-Control'));

        $codigos = $resposta->json('data.codigos');
        $this->assertCount(8, $codigos);
        $this->assertCount(8, array_unique($codigos));
        foreach ($codigos as $codigo) {
            $this->assertMatchesRegularExpression(self::PADRAO_CODIGO_DE_RECUPERACAO, $codigo);
        }
        $this->assertTrue($gestor->fresh()->doisFatoresAtivo());

        // Ativar de novo, já ativo, não recomeça: o segredo em uso não pode ser trocado por engano.
        $this->postJson('/api/2fa/iniciar', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('dois_fatores');
    }

    public function test_confirmar_sem_ter_iniciado_falha(): void
    {
        $this->comoUsuario($this->criarGestor());

        $this->postJson('/api/2fa/confirmar', ['codigo' => '123456'])->assertUnprocessable()->assertJsonValidationErrors('codigo');
    }

    public function test_o_perfil_mostra_so_os_indicadores_e_nunca_o_segredo(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo, 'codigos' => $codigos] = $this->ativarDoisFatores($gestor);
        $this->comoUsuario($gestor);

        $resposta = $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('two_factor_ativo', true)
            ->assertJsonPath('two_factor_obrigatorio', false)
            ->assertJsonPath('two_factor_codigos_restantes', 8);

        $this->assertStringNotContainsString($segredo, $resposta->getContent());
        $this->assertStringNotContainsString($codigos[0], $resposta->getContent());
        $resposta->assertJsonMissingPath('two_factor_secret')->assertJsonMissingPath('two_factor_recovery_codes');
    }

    public function test_login_com_2fa_nao_emite_token_ate_o_codigo_ser_informado(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo] = $this->ativarDoisFatores($gestor);

        $passo1 = $this->entrar($gestor->email)->assertOk()
            ->assertJsonPath('dois_fatores', true)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user');
        $desafio = $passo1->json('desafio');
        $this->assertSame(48, strlen($desafio));
        $this->assertSame(0, $gestor->tokens()->count());

        $passo2 = $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $this->codigoAtual($segredo)])
            ->assertOk()
            ->assertJsonPath('user.id', $gestor->id)
            ->assertJsonStructure(['token']);
        $this->semSessao();

        $this->withToken($passo2->json('token'))->getJson('/api/convenios')->assertOk();
        $this->assertSame(1, $gestor->tokens()->count());

        // O desafio é de uso único: depois de concluído não vale para outro token.
        $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $this->codigoVizinho($segredo, 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('desafio');
    }

    public function test_senha_errada_nao_gera_desafio(): void
    {
        $gestor = $this->criarGestor();
        $this->ativarDoisFatores($gestor);

        $this->entrar($gestor->email, 'senha-errada')->assertUnprocessable()->assertJsonMissingPath('desafio');
    }

    public function test_codigo_errado_recusa_e_depois_de_cinco_tentativas_o_desafio_se_apaga(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo] = $this->ativarDoisFatores($gestor);
        $desafio = $this->entrar($gestor->email)->json('desafio');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => '000000'])
                ->assertUnprocessable()->assertJsonValidationErrors('codigo');
        }

        // Sexta tentativa: já não adianta nem o código certo.
        $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $this->codigoAtual($segredo)])
            ->assertUnprocessable()->assertJsonValidationErrors('desafio');
        $this->assertSame(0, $gestor->tokens()->count());
    }

    public function test_desafio_inexistente_ou_de_outra_pessoa_nao_vale(): void
    {
        $this->postJson('/api/login/2fa', ['desafio' => str_repeat('a', 48), 'codigo' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('desafio');

        $this->postJson('/api/login/2fa', ['desafio' => 'curto', 'codigo' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('desafio');
    }

    public function test_o_mesmo_codigo_do_app_nao_entra_duas_vezes(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo] = $this->ativarDoisFatores($gestor);
        $codigo = $this->codigoAtual($segredo);

        $primeiro = $this->entrar($gestor->email)->json('desafio');
        $this->postJson('/api/login/2fa', ['desafio' => $primeiro, 'codigo' => $codigo])->assertOk();

        $segundo = $this->entrar($gestor->email)->json('desafio');
        $this->postJson('/api/login/2fa', ['desafio' => $segundo, 'codigo' => $codigo])
            ->assertUnprocessable()->assertJsonValidationErrors('codigo');

        // Um período seguinte, sim.
        $this->postJson('/api/login/2fa', ['desafio' => $segundo, 'codigo' => $this->codigoVizinho($segredo, 1)])->assertOk();
    }

    public function test_a_tolerancia_e_de_um_periodo_para_cada_lado_e_nao_mais(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo] = $this->ativarDoisFatores($gestor);

        $this->assertTrue(app(DoisFatoresService::class)->verificar($gestor->fresh(), $this->codigoVizinho($segredo, -1)));
        $gestor->forceFill(['two_factor_ultimo_passo' => null])->save();
        $this->assertFalse(app(DoisFatoresService::class)->verificar($gestor->fresh(), $this->codigoVizinho($segredo, 3)));
        $this->assertFalse(app(DoisFatoresService::class)->verificar($gestor->fresh(), $this->codigoVizinho($segredo, -3)));
    }

    public function test_codigo_de_recuperacao_entra_uma_vez_e_some(): void
    {
        $gestor = $this->criarGestor();
        ['codigos' => $codigos] = $this->ativarDoisFatores($gestor);

        $desafio = $this->entrar($gestor->email)->json('desafio');
        // Digitado em minúsculas e sem o hífen: a tela não deve punir quem digita como ouve.
        $digitado = strtolower(str_replace('-', '', $codigos[3]));
        $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $digitado])->assertOk()->assertJsonStructure(['token']);

        $this->assertSame(7, $gestor->fresh()->two_factor_codigos_restantes);

        $novo = $this->entrar($gestor->email)->json('desafio');
        $this->postJson('/api/login/2fa', ['desafio' => $novo, 'codigo' => $codigos[3]])
            ->assertUnprocessable()->assertJsonValidationErrors('codigo');

        // Outro código continua valendo.
        $this->postJson('/api/login/2fa', ['desafio' => $novo, 'codigo' => $codigos[4]])->assertOk();
        $this->assertSame(6, $gestor->fresh()->two_factor_codigos_restantes);
    }

    public function test_codigo_de_recuperacao_so_e_guardado_como_hash(): void
    {
        $gestor = $this->criarGestor();
        ['codigos' => $codigos] = $this->ativarDoisFatores($gestor);

        $guardados = $gestor->fresh()->two_factor_recovery_codes;

        $this->assertCount(8, $guardados);
        foreach ($guardados as $guardado) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $guardado);
            $this->assertNotContains($guardado, $codigos);
        }
    }

    public function test_depois_de_dez_erros_a_pessoa_fica_bloqueada_mesmo_com_o_codigo_certo(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo] = $this->ativarDoisFatores($gestor);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('2fa:'.$gestor->id, 600);
        }

        $desafio = $this->entrar($gestor->email)->json('desafio');
        $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $this->codigoAtual($segredo)])->assertStatus(429);
        $this->assertSame(0, $gestor->tokens()->count());
    }

    public function test_conta_desativada_entre_a_senha_e_o_codigo_nao_recebe_token(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo] = $this->ativarDoisFatores($gestor);
        $desafio = $this->entrar($gestor->email)->json('desafio');

        $gestor->forceFill(['active' => false])->save();

        $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $this->codigoAtual($segredo)])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(0, $gestor->tokens()->count());
    }

    public function test_esqueci_a_senha_nao_e_um_atalho_em_volta_do_2fa(): void
    {
        $gestor = $this->criarGestor();
        $this->ativarDoisFatores($gestor);
        $token = app('auth.password.broker')->createToken($gestor);
        $this->semSessao();

        $this->postJson('/api/redefinir-senha', ['email' => $gestor->email, 'token' => $token, 'password' => 'MinhaSenhaPropria99', 'password_confirmation' => 'MinhaSenhaPropria99'])->assertOk();

        $this->assertTrue($gestor->fresh()->doisFatoresAtivo());
        $this->entrar($gestor->email, 'MinhaSenhaPropria99')->assertJsonPath('dois_fatores', true)->assertJsonMissingPath('token');
    }

    public function test_administradores_sem_2fa_so_passam_pela_ativacao_quando_e_obrigatorio(): void
    {
        config(['seguranca.dois_fatores_obrigatorio' => true]);
        $super = $this->criarAdmin();
        $adminPrefeitura = $this->criarAdminDaPrefeitura();

        foreach ([$super, $adminPrefeitura] as $administrador) {
            $this->comoUsuario($administrador);

            // Bloqueado: qualquer tela de trabalho responde 403 com o código que o sistema entende.
            foreach (['/api/convenios', '/api/dashboard', '/api/users', '/api/audits'] as $rota) {
                $this->getJson($rota)->assertForbidden()->assertJsonPath('codigo', '2fa_obrigatorio');
            }

            // Liberado: ver quem é (com o aviso), trocar a senha e ativar o 2FA.
            $this->getJson('/api/me')->assertOk()->assertJsonPath('two_factor_obrigatorio', true)->assertJsonPath('two_factor_ativo', false);
            $this->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'OutraSenhaBoa2026'])->assertOk();
            $this->postJson('/api/2fa/iniciar', ['password' => 'OutraSenhaBoa2026'])->assertOk();
        }
    }

    public function test_quem_ainda_nao_ativou_o_2fa_consegue_sair(): void
    {
        config(['seguranca.dois_fatores_obrigatorio' => true]);
        $token = $this->criarAdminDaPrefeitura()->createToken('aparelho')->plainTextToken;
        $this->semSessao();

        $this->withToken($token)->getJson('/api/convenios')->assertForbidden()->assertJsonPath('codigo', '2fa_obrigatorio');
        $this->withToken($token)->postJson('/api/logout')->assertOk();
    }

    public function test_depois_de_ativar_o_administrador_trabalha_normalmente(): void
    {
        config(['seguranca.dois_fatores_obrigatorio' => true]);
        $administrador = $this->criarAdminDaPrefeitura();
        $this->comoUsuario($administrador);

        $segredo = $this->postJson('/api/2fa/iniciar', ['password' => 'password'])->json('data.segredo');
        $this->getJson('/api/dashboard')->assertForbidden();
        $this->postJson('/api/2fa/confirmar', ['codigo' => $this->codigoAtual($segredo)])->assertOk();

        $this->getJson('/api/dashboard')->assertOk();
    }

    public function test_gestor_e_fiscal_nao_sao_obrigados_e_o_desligamento_da_regra_libera_os_administradores(): void
    {
        config(['seguranca.dois_fatores_obrigatorio' => true]);
        $this->comoUsuario($this->criarGestor());
        $this->getJson('/api/dashboard')->assertOk();
        $this->comoUsuario($this->criarFiscal());
        $this->getJson('/api/dashboard')->assertOk();

        config(['seguranca.dois_fatores_obrigatorio' => false]);
        $this->comoUsuario($this->criarAdminDaPrefeitura());
        $this->getJson('/api/dashboard')->assertOk();
    }

    public function test_a_troca_da_senha_temporaria_vem_antes_da_ativacao_do_2fa(): void
    {
        config(['seguranca.dois_fatores_obrigatorio' => true]);
        $administrador = $this->criarAdminDaPrefeitura();
        $administrador->forceFill(['must_change_password' => true, 'senha_temporaria_expira_em' => now()->addDay()])->save();
        $this->comoUsuario($administrador);

        $this->postJson('/api/2fa/iniciar', ['password' => 'password'])->assertForbidden()->assertJsonPath('codigo', 'senha_temporaria');
        $this->getJson('/api/dashboard')->assertForbidden()->assertJsonPath('codigo', 'senha_temporaria');
    }

    public function test_gerar_novos_codigos_pede_a_senha_e_invalida_os_antigos(): void
    {
        $gestor = $this->criarGestor();
        ['codigos' => $antigos] = $this->ativarDoisFatores($gestor);
        $this->comoUsuario($gestor);

        $this->postJson('/api/2fa/codigos-recuperacao', ['password' => 'errada-mesmo'])->assertUnprocessable();

        $resposta = $this->postJson('/api/2fa/codigos-recuperacao', ['password' => 'password'])->assertOk();
        $this->assertStringContainsString('no-store', $resposta->headers->get('Cache-Control'));
        $novos = $resposta->json('data.codigos');

        $this->assertCount(8, $novos);
        $this->assertEmpty(array_intersect($antigos, $novos));

        $desafio = $this->entrar($gestor->email)->json('desafio');
        $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $antigos[0]])->assertUnprocessable();
        $this->postJson('/api/login/2fa', ['desafio' => $desafio, 'codigo' => $novos[0]])->assertOk();
    }

    public function test_gerar_codigos_sem_2fa_ativo_falha(): void
    {
        $this->comoUsuario($this->criarGestor());

        $this->postJson('/api/2fa/codigos-recuperacao', ['password' => 'password'])->assertUnprocessable();
    }

    public function test_gestor_desativa_com_senha_e_codigo_e_as_outras_sessoes_caem(): void
    {
        $gestor = $this->criarGestor();
        ['segredo' => $segredo] = $this->ativarDoisFatores($gestor);
        $outra = $gestor->createToken('outro-aparelho');
        $this->comoUsuario($gestor);

        $this->deleteJson('/api/2fa', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->deleteJson('/api/2fa', ['password' => 'errada-mesmo', 'codigo' => $this->codigoAtual($segredo)])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->deleteJson('/api/2fa', ['password' => 'password', 'codigo' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->assertTrue($gestor->fresh()->doisFatoresAtivo());

        $this->deleteJson('/api/2fa', ['password' => 'password', 'codigo' => $this->codigoAtual($segredo)])->assertOk();

        $gestor->refresh();
        $this->assertFalse($gestor->doisFatoresAtivo());
        $this->assertNull($gestor->two_factor_secret);
        $this->assertNull($gestor->two_factor_recovery_codes);
        $this->assertNull($gestor->two_factor_ultimo_passo);
        $this->assertNull($gestor->tokens()->find($outra->accessToken->id));
        $this->entrar($gestor->email)->assertJsonMissingPath('dois_fatores')->assertJsonStructure(['token']);
    }

    public function test_perfil_obrigado_a_usar_2fa_nao_consegue_desativar(): void
    {
        config(['seguranca.dois_fatores_obrigatorio' => true]);
        $administrador = $this->criarAdminDaPrefeitura();
        ['segredo' => $segredo] = $this->ativarDoisFatores($administrador);
        $this->comoUsuario($administrador);

        $this->deleteJson('/api/2fa', ['password' => 'password', 'codigo' => $this->codigoAtual($segredo)])
            ->assertUnprocessable()->assertJsonValidationErrors('dois_fatores');
        $this->assertTrue($administrador->fresh()->doisFatoresAtivo());
    }

    public function test_super_administrador_redefine_o_2fa_de_um_administrador_de_prefeitura_com_a_propria_senha(): void
    {
        $alvo = $this->criarAdminDaPrefeitura();
        $this->ativarDoisFatores($alvo);
        $token = $alvo->createToken('aparelho');
        $this->comoUsuario($this->criarAdmin());

        $this->postJson("/api/users/{$alvo->id}/redefinir-2fa")->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson("/api/users/{$alvo->id}/redefinir-2fa", ['password' => 'errada-mesmo'])->assertUnprocessable();
        $this->assertTrue($alvo->fresh()->doisFatoresAtivo());

        $this->postJson("/api/users/{$alvo->id}/redefinir-2fa", ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_ativo', false);

        $alvo->refresh();
        $this->assertFalse($alvo->doisFatoresAtivo());
        $this->assertNull($alvo->two_factor_secret);
        $this->assertSame(0, $alvo->tokens()->count());
        $this->assertNull($alvo->tokens()->find($token->accessToken->id));
    }

    public function test_administrador_da_prefeitura_redefine_o_2fa_de_gestor_e_fiscal_da_propria_prefeitura(): void
    {
        $tenant = $this->criarTenant();
        $admin = $this->criarAdminDaPrefeitura($tenant);
        $gestor = $this->criarGestor($tenant);
        $fiscal = $this->criarFiscal($tenant);
        $this->ativarDoisFatores($gestor);
        $this->ativarDoisFatores($fiscal);
        $this->comoUsuario($admin);

        foreach ([$gestor, $fiscal] as $alvo) {
            $this->postJson("/api/users/{$alvo->id}/redefinir-2fa", ['password' => 'password'])->assertOk();
            $this->assertFalse($alvo->fresh()->doisFatoresAtivo());
        }
    }

    public function test_administrador_da_prefeitura_nao_redefine_o_2fa_do_colega_administrador_de_outra_prefeitura_nem_o_proprio(): void
    {
        $tenant = $this->criarTenant();
        $admin = $this->criarAdminDaPrefeitura($tenant);
        $colega = $this->criarAdminDaPrefeitura($tenant);
        $deOutraPrefeitura = $this->criarGestor();
        foreach ([$admin, $colega, $deOutraPrefeitura] as $u) {
            $this->ativarDoisFatores($u);
        }
        $this->comoUsuario($admin);

        foreach ([$colega, $deOutraPrefeitura, $admin] as $alvo) {
            $this->postJson("/api/users/{$alvo->id}/redefinir-2fa", ['password' => 'password'])->assertForbidden();
            $this->assertTrue($alvo->fresh()->doisFatoresAtivo());
        }
    }

    public function test_gestor_e_super_administrador_alvo_nao_entram_no_redefinir_2fa(): void
    {
        $tenant = $this->criarTenant();
        $gestor = $this->criarGestor($tenant);
        $colega = $this->criarFiscal($tenant);
        $this->ativarDoisFatores($colega);
        $this->comoUsuario($gestor);
        $this->postJson("/api/users/{$colega->id}/redefinir-2fa", ['password' => 'password'])->assertForbidden();

        // A conta do super administrador só se mexe pelo servidor: nem outro super administrador passa pela API.
        $super = $this->criarAdmin();
        $outroSuper = $this->criarAdmin();
        $this->ativarDoisFatores($super);
        $this->comoUsuario($outroSuper);
        $this->postJson("/api/users/{$super->id}/redefinir-2fa", ['password' => 'password'])->assertNotFound();
        $this->assertTrue($super->fresh()->doisFatoresAtivo());
    }

    public function test_a_lista_de_usuarios_informa_quem_tem_2fa(): void
    {
        $tenant = $this->criarTenant();
        $com = $this->criarGestor($tenant);
        $sem = $this->criarFiscal($tenant);
        $this->ativarDoisFatores($com);
        $this->comoUsuario($this->criarAdmin());

        $linhas = collect($this->getJson('/api/users?tenant_id='.$tenant->id)->json('data'))->keyBy('id');

        $this->assertTrue($linhas[$com->id]['two_factor_ativo']);
        $this->assertFalse($linhas[$sem->id]['two_factor_ativo']);
    }

    public function test_o_comando_do_servidor_redefine_o_2fa_do_super_administrador(): void
    {
        $super = $this->criarAdmin();
        $this->ativarDoisFatores($super);
        $token = $super->createToken('aparelho');

        $this->artisan('admin:redefinir-2fa', ['email' => 'ninguem@exemplo.gov.br'])->expectsOutputToContain('Nenhuma conta')->assertFailed();
        $this->artisan('admin:redefinir-2fa', ['email' => $super->email])->expectsConfirmation('Apagar o 2FA de '.$super->email.' (Super Administrador)? A pessoa será desconectada.', 'no')->assertFailed();
        $this->assertTrue($super->fresh()->doisFatoresAtivo());

        $this->artisan('admin:redefinir-2fa', ['email' => $super->email])->expectsConfirmation('Apagar o 2FA de '.$super->email.' (Super Administrador)? A pessoa será desconectada.', 'yes')->assertSuccessful();

        $this->assertFalse($super->fresh()->doisFatoresAtivo());
        $this->assertNull($super->tokens()->find($token->accessToken->id));
    }

    public function test_a_auditoria_registra_a_ativacao_mas_nunca_o_segredo_nem_os_codigos(): void
    {
        config(['audit.console' => true]);
        $gestor = $this->criarGestor();
        ['segredo' => $segredo, 'codigos' => $codigos] = $this->ativarDoisFatores($gestor);

        $registros = $gestor->audits()->get();
        $texto = json_encode($registros->map(fn ($a) => [$a->old_values, $a->new_values]));

        $this->assertStringContainsString('two_factor_confirmed_at', $texto);
        $this->assertStringNotContainsString($segredo, $texto);
        $this->assertStringNotContainsString($codigos[0], $texto);
        $this->assertStringNotContainsString('two_factor_secret', $texto);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $texto);
    }
}
