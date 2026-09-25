<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Verificação em duas etapas por app autenticador (TOTP, RFC 6238: Google Authenticator, Microsoft
 * Authenticator, Authy, 1Password...). Regras:
 *  - o segredo só vale depois de a pessoa digitar um código do app (confirmação);
 *  - cada código de 30 s entra uma vez só (guarda-se o último período aceito), e a tolerância é de
 *    um período para cada lado (relógios desencontrados);
 *  - os códigos de recuperação são de uso único e ficam só como hash com chave (APP_KEY);
 *  - no login, o e-mail e a senha corretos não emitem token: emitem um "desafio" curto, que só o
 *    código completa.
 */
class DoisFatoresService
{
    private const VALIDADE_DO_DESAFIO_MINUTOS = 5;

    private const TENTATIVAS_POR_DESAFIO = 5;

    private const QUANTIDADE_DE_CODIGOS = 8;

    // Sem os caracteres que se confundem (0/O, 1/I).
    private const ALFABETO_DOS_CODIGOS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Começa (ou recomeça) a ativação: gera um segredo novo, ainda sem valor até a confirmação.
     *
     * @return array{segredo: string, url: string} O segredo para digitar no app e o endereço otpauth:// do QR Code.
     */
    public function iniciar(User $usuario): array
    {
        $this->impedirSeJaAtivo($usuario);

        $segredo = $this->google2fa->generateSecretKey();

        $usuario->forceFill([
            'two_factor_secret' => $segredo,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_ultimo_passo' => null,
        ])->save();

        return ['segredo' => $segredo, 'url' => $this->enderecoOtpauth($usuario, $segredo)];
    }

    /**
     * A pessoa digitou o primeiro código do app: o 2FA passa a valer e nascem os códigos de recuperação.
     *
     * @return array<int, string> Os códigos de recuperação em texto, mostrados uma única vez.
     */
    public function confirmar(User $usuario, string $codigo): array
    {
        $this->impedirSeJaAtivo($usuario);

        if ($usuario->two_factor_secret === null) {
            throw ValidationException::withMessages(['codigo' => 'Comece a ativação antes de confirmar o código.']);
        }

        if (! $this->verificarTotp($usuario, $codigo)) {
            throw ValidationException::withMessages(['codigo' => 'Código inválido ou vencido. Confira o app e o horário do celular.']);
        }

        $usuario->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->gerarCodigosDeRecuperacao($usuario);
    }

    /**
     * Confere um código do app (6 dígitos) ou um código de recuperação (XXXXX-XXXXX). Um código do app
     * já usado, ou de um período anterior ao último aceito, não vale de novo.
     */
    public function verificar(User $usuario, string $codigo): bool
    {
        if (! $usuario->doisFatoresAtivo()) {
            return false;
        }

        $limpo = preg_replace('/\s+/', '', $codigo) ?? '';

        return preg_match('/^\d{6}$/', $limpo) === 1
            ? $this->verificarTotp($usuario, $limpo)
            : $this->usarCodigoDeRecuperacao($usuario, $limpo);
    }

    /**
     * Novos códigos de recuperação: os antigos, usados ou não, deixam de valer.
     *
     * @return array<int, string>
     */
    public function gerarCodigosDeRecuperacao(User $usuario): array
    {
        $codigos = [];

        for ($i = 0; $i < self::QUANTIDADE_DE_CODIGOS; $i++) {
            $codigo = '';

            for ($j = 0; $j < 10; $j++) {
                $codigo .= self::ALFABETO_DOS_CODIGOS[random_int(0, strlen(self::ALFABETO_DOS_CODIGOS) - 1)];
            }

            $codigos[] = substr($codigo, 0, 5).'-'.substr($codigo, 5);
        }

        $usuario->forceFill(['two_factor_recovery_codes' => array_map(fn (string $c) => $this->hashDoCodigo($c), $codigos)])->save();

        return $codigos;
    }

    public function codigosDeRecuperacaoRestantes(User $usuario): int
    {
        return count($usuario->two_factor_recovery_codes ?? []);
    }

    /** A própria pessoa desativa (onde o perfil permite): apaga segredo, códigos e o registro do último período. */
    public function desativar(User $usuario): void
    {
        $usuario->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_ultimo_passo' => null,
        ])->save();
    }

    /**
     * Um administrador (ou o comando do servidor) redefine o 2FA de quem perdeu o celular e os códigos:
     * apaga tudo e derruba as sessões. Onde o 2FA é obrigatório, a pessoa ativa de novo no próximo acesso.
     */
    public function redefinir(User $usuario, ?User $autor = null): void
    {
        $this->desativar($usuario);
        $usuario->tokens()->delete();

        Log::warning('2FA redefinido', ['user_id' => $usuario->id, 'por' => $autor?->id ?? 'servidor']);
    }

    /** Depois de e-mail e senha corretos: um código de uso único (5 min) que só a verificação do 2FA completa. */
    public function criarDesafio(User $usuario, string $dispositivo): string
    {
        $desafio = Str::random(48);

        Cache::put(
            $this->chaveDoDesafio($desafio),
            ['user_id' => $usuario->id, 'dispositivo' => $dispositivo],
            now()->addMinutes(self::VALIDADE_DO_DESAFIO_MINUTOS),
        );

        return $desafio;
    }

    /**
     * Confere o desafio e conta a tentativa: depois de 5 tentativas (certas ou erradas) ele se apaga e a
     * pessoa recomeça pelo e-mail e senha.
     *
     * @return array{usuario: User, dispositivo: string}
     */
    public function resolverDesafio(string $desafio): array
    {
        $chave = $this->chaveDoDesafio($desafio);
        $dados = Cache::get($chave);
        $usuario = is_array($dados) ? User::query()->find($dados['user_id']) : null;

        if (! $usuario?->doisFatoresAtivo()) {
            throw $this->desafioInvalido();
        }

        Cache::add($chave.':tentativas', 0, now()->addMinutes(self::VALIDADE_DO_DESAFIO_MINUTOS));

        if (Cache::increment($chave.':tentativas') > self::TENTATIVAS_POR_DESAFIO) {
            $this->encerrarDesafio($desafio);

            throw $this->desafioInvalido();
        }

        return ['usuario' => $usuario, 'dispositivo' => (string) $dados['dispositivo']];
    }

    public function encerrarDesafio(string $desafio): void
    {
        $chave = $this->chaveDoDesafio($desafio);

        Cache::forget($chave);
        Cache::forget($chave.':tentativas');
    }

    private function verificarTotp(User $usuario, string $codigo): bool
    {
        if (preg_match('/^\d{6}$/', $codigo) !== 1) {
            return false;
        }

        // Trava a linha: dois pedidos simultâneos com o mesmo código não passam os dois.
        return DB::transaction(function () use ($usuario, $codigo): bool {
            $atual = User::query()->lockForUpdate()->find($usuario->id);

            if ($atual?->two_factor_secret === null) {
                return false;
            }

            $passo = $this->google2fa->verifyKeyNewer($atual->two_factor_secret, $codigo, (int) $atual->two_factor_ultimo_passo, 1);

            if ($passo === false) {
                return false;
            }

            $atual->forceFill(['two_factor_ultimo_passo' => $passo])->save();
            $this->sincronizar($usuario, $atual, 'two_factor_ultimo_passo');

            return true;
        });
    }

    private function usarCodigoDeRecuperacao(User $usuario, string $codigo): bool
    {
        $normalizado = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');

        if (strlen($normalizado) !== 10) {
            return false;
        }

        $hash = $this->hashDoCodigo(substr($normalizado, 0, 5).'-'.substr($normalizado, 5));

        return DB::transaction(function () use ($usuario, $hash): bool {
            $atual = User::query()->lockForUpdate()->find($usuario->id);
            $codigos = $atual?->two_factor_recovery_codes ?? [];
            $achado = null;

            foreach ($codigos as $indice => $existente) {
                if (hash_equals($existente, $hash)) {
                    $achado = $indice;
                }
            }

            if ($achado === null) {
                return false;
            }

            unset($codigos[$achado]);
            $atual->forceFill(['two_factor_recovery_codes' => array_values($codigos)])->save();
            $this->sincronizar($usuario, $atual, 'two_factor_recovery_codes');

            return true;
        });
    }

    /**
     * A linha foi gravada por outra instância (a travada da transação): copia o valor novo para o usuário em
     * memória como "já gravado". Sem isso, apagar o campo depois pareceria "sem mudança" e não iria ao banco.
     */
    private function sincronizar(User $usuario, User $gravado, string ...$campos): void
    {
        $usuario->setRawAttributes(array_merge($usuario->getAttributes(), Arr::only($gravado->getAttributes(), $campos)), true);
    }

    private function hashDoCodigo(string $codigo): string
    {
        return hash_hmac('sha256', $codigo, (string) config('app.key'));
    }

    private function chaveDoDesafio(string $desafio): string
    {
        return '2fa:desafio:'.hash('sha256', $desafio);
    }

    private function desafioInvalido(): ValidationException
    {
        return ValidationException::withMessages(['desafio' => 'A verificação expirou. Entre novamente com e-mail e senha.']);
    }

    private function impedirSeJaAtivo(User $usuario): void
    {
        if ($usuario->doisFatoresAtivo()) {
            throw ValidationException::withMessages(['dois_fatores' => 'A verificação em duas etapas já está ativa nesta conta.']);
        }
    }

    private function enderecoOtpauth(User $usuario, string $segredo): string
    {
        $emissor = (string) config('app.name');

        return 'otpauth://totp/'.rawurlencode($emissor.':'.$usuario->email).'?'.http_build_query([
            'secret' => $segredo,
            'issuer' => $emissor,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
