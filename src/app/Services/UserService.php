<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Laravel\Sanctum\PersonalAccessToken;

class UserService
{
    /**
     * tenant_id e role fora do #[Fillable] do User: são atribuídos aqui,
     * depois de validados pelo Form Request.
     */
    public function criar(array $dados): User
    {
        $usuario = new User(Arr::only($dados, ['name', 'email', 'password']));
        $usuario->forceFill([
            'tenant_id' => $dados['tenant_id'],
            'role' => $dados['role'],
            'active' => true,
            // A senha inicial foi escolhida por outra pessoa (o administrador): a troca é obrigatória.
            'must_change_password' => true,
        ])->save();

        return $usuario;
    }

    /**
     * Desativar a conta ou trocar a senha revoga todos os tokens emitidos:
     * o efeito é imediato, sem esperar o token expirar.
     */
    public function atualizar(User $usuario, array $dados): User
    {
        $usuario->fill(Arr::only($dados, ['name', 'email', 'password']));
        $usuario->forceFill(Arr::only($dados, ['role', 'active']));

        // Senha redefinida por um administrador é temporária: quem a recebe troca no próximo acesso.
        if ($usuario->isDirty('password')) {
            $usuario->forceFill(['must_change_password' => true]);
        }

        $revogarTokens = $usuario->isDirty('password')
            || ($usuario->isDirty('active') && ! $usuario->active);

        $usuario->save();

        if ($revogarTokens) {
            $usuario->tokens()->delete();
        }

        return $usuario;
    }

    /**
     * Troca a senha do próprio usuário. Todos os outros tokens (outros
     * dispositivos/sessões) são revogados; só o token atual continua valendo.
     */
    public function alterarSenha(User $usuario, string $novaSenha): void
    {
        // A pessoa acabou de escolher a própria senha: a exigência de troca (se havia) termina aqui.
        $usuario->forceFill(['password' => $novaSenha, 'must_change_password' => false])->save();

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
        $usuario->forceFill(['password' => $novaSenha, 'must_change_password' => false])->save();
        $usuario->tokens()->delete();
    }
}
