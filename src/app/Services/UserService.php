<?php

namespace App\Services;

use App\Models\User;
use App\Support\SenhaTemporaria;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class UserService
{
    /**
     * A senha inicial é gerada aqui (aleatória) e devolvida UMA vez, para quem criou a conta repassar
     * por um canal seguro. Ela não é guardada em lugar nenhum além do hash e o usuário é obrigado a
     * trocá-la no primeiro acesso. tenant_id e role ficam fora do #[Fillable] do User: são atribuídos
     * aqui, depois de validados pelo Form Request.
     *
     * @return array{0: User, 1: string} O usuário e a senha temporária em texto.
     */
    public function criar(array $dados): array
    {
        $senha = SenhaTemporaria::gerar();

        $usuario = new User(['name' => $dados['name'], 'email' => $dados['email'], 'password' => $senha]);
        $usuario->forceFill([
            'tenant_id' => $dados['tenant_id'],
            'role' => $dados['role'],
            'active' => true,
            'must_change_password' => true,
            'senha_temporaria_expira_em' => $this->prazoDaSenhaTemporaria(),
        ])->save();

        return [$usuario, $senha];
    }

    /**
     * Desativar a conta revoga todos os tokens emitidos: o efeito é imediato, sem esperar o token expirar.
     * Quem não é o super administrador (o administrador da prefeitura) não pode se desativar nem mudar o
     * próprio papel.
     */
    public function atualizar(User $usuario, array $dados, ?User $autor = null): User
    {
        $usuario->fill(Arr::only($dados, ['name', 'email']));
        $usuario->forceFill(Arr::only($dados, ['role', 'active']));

        if ($autor && ! $autor->isAdministradorInterno()) {
            $this->impedirAutoPrejuizo($usuario, $autor);
        }

        $revogarTokens = $usuario->isDirty('active') && ! $usuario->active;

        $usuario->save();

        if ($revogarTokens) {
            $usuario->tokens()->delete();
        }

        return $usuario;
    }

    /**
     * Botão "Redefinir senha": nova senha temporária aleatória (mostrada uma vez), troca obrigatória
     * no próximo acesso e todas as sessões da pessoa caem na hora.
     *
     * @return string A senha temporária em texto.
     */
    public function redefinirParaTemporaria(User $usuario, User $autor): string
    {
        $senha = SenhaTemporaria::gerar();

        $usuario->forceFill([
            'password' => $senha,
            'must_change_password' => true,
            'senha_temporaria_expira_em' => $this->prazoDaSenhaTemporaria(),
        ])->save();
        $usuario->tokens()->delete();

        Log::info('Senha redefinida para temporária por um administrador', ['user_id' => $usuario->id, 'por' => $autor->id]);

        return $senha;
    }

    /**
     * Troca a senha do próprio usuário. Todos os outros tokens (outros
     * dispositivos/sessões) são revogados; só o token atual continua valendo.
     */
    public function alterarSenha(User $usuario, string $novaSenha): void
    {
        // A pessoa acabou de escolher a própria senha: a exigência de troca (se havia) termina aqui.
        $usuario->forceFill(['password' => $novaSenha, 'must_change_password' => false, 'senha_temporaria_expira_em' => null])->save();

        $this->revogarOutrosTokens($usuario);
    }

    /** Derruba as outras sessões da pessoa (outros dispositivos); só o token desta requisição continua. */
    public function revogarOutrosTokens(User $usuario): void
    {
        // Fora de uma requisição por token (ex.: testes com actingAs) não há token "atual" a preservar.
        $atual = $usuario->currentAccessToken();
        $usuario->tokens()
            ->when($atual instanceof PersonalAccessToken, fn ($query) => $query->where('id', '!=', $atual->getKey()))
            ->delete();
    }

    /**
     * Senha criada pelo link do e-mail ("esqueci minha senha"). Quem chega aqui não estava logado,
     * então NENHUM token é preservado: se alguém mais tinha entrado com a senha antiga, cai.
     */
    public function redefinirPorLink(User $usuario, string $novaSenha): void
    {
        $usuario->forceFill(['password' => $novaSenha, 'must_change_password' => false, 'senha_temporaria_expira_em' => null])->save();
        $usuario->tokens()->delete();
    }

    private function prazoDaSenhaTemporaria(): Carbon
    {
        return now()->addDays(config('seguranca.senha_temporaria_dias'));
    }

    /**
     * O administrador da prefeitura não pode se desativar nem mudar o próprio papel. Só quem é administrador
     * ativo mexe em contas, e ninguém mexe na própria: assim a prefeitura sempre mantém pelo menos um
     * administrador ativo (o super administrador é quem resolve o caso extremo).
     */
    private function impedirAutoPrejuizo(User $alvo, User $autor): void
    {
        $desativando = $alvo->isDirty('active') && ! $alvo->active;

        if ($alvo->is($autor) && ($desativando || $alvo->isDirty('role'))) {
            throw ValidationException::withMessages([
                'active' => 'Você não pode desativar a própria conta nem mudar o próprio papel. Peça a outro administrador.',
            ]);
        }
    }
}
