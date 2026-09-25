<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Notifications\RedefinirSenha;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * tenant_id, role e active nunca são preenchíveis em massa: papel, prefeitura
 * e situação de um usuário só podem ser definidos por um Administrador
 * Interno através do UserService, nunca por payload direto do cliente.
 * Senha, segredo e códigos de recuperação do 2FA ficam fora da trilha de auditoria ($auditExclude); a ativação
 * e a desativação do 2FA aparecem nela pela data de confirmação.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_ultimo_passo'])]
// A tela decide o que mostrar (Segurança, aviso de 2FA obrigatório) a partir destes dois indicadores.
#[Appends(['two_factor_ativo', 'two_factor_obrigatorio', 'two_factor_codigos_restantes'])]
#[UsePolicy(UserPolicy::class)]
#[UseResource(UserResource::class)]
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    /** @var array<int, string> */
    protected $auditExclude = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_ultimo_passo'];

    /**
     * Espelha o default da coluna: um User recém-criado em memória (antes de
     * ser recarregado do banco) já nasce ativo.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
        'must_change_password' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            // Senha temporária (conta nova ou redefinida por um administrador): só o UserService liga e desliga.
            'must_change_password' => 'boolean',
            'senha_temporaria_expira_em' => 'datetime',
            // Segredo do app autenticador e hashes dos códigos de recuperação: criptografados com a APP_KEY.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Para onde vão os e-mails ao usuário. Com ALERTAS_REDIRECIONAR_PARA preenchido (desenvolvimento e
     * homologação) tudo vai para esse endereço, como já acontece com os alertas: um teste nunca escreve
     * para o e-mail de outra pessoa. Em produção a variável fica vazia.
     */
    public function routeNotificationForMail(): string
    {
        return filled(config('alertas.redirecionar_para')) ? config('alertas.redirecionar_para') : $this->email;
    }

    /** E-mail em português com o link da tela de redefinição (em vez do padrão do framework). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RedefinirSenha($token));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Alertas de prazo que este usuário já leu no sino do sistema. */
    public function alertasLidos(): BelongsToMany
    {
        return $this->belongsToMany(AlertaPrazo::class, 'alerta_prazo_leituras')->withPivot('lido_em');
    }

    /**
     * A senha temporária venceu: o login é recusado e um administrador precisa gerar outra. Sem prazo
     * gravado conta como vencida (falha para o lado seguro: quem esquecer de gravar o prazo vai notar).
     */
    public function senhaTemporariaExpirada(): bool
    {
        return $this->must_change_password
            && ($this->senha_temporaria_expira_em === null || $this->senha_temporaria_expira_em->isPast());
    }

    /** O 2FA está valendo: a pessoa já provou ter o app autenticador (o segredo sozinho, sem confirmação, não conta). */
    public function doisFatoresAtivo(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** O perfil é obrigado a usar o 2FA (e a regra está ligada; só o desenvolvimento a desliga). */
    public function exigeDoisFatores(): bool
    {
        return config('seguranca.dois_fatores_obrigatorio') && $this->role->exigeDoisFatores();
    }

    /** Falta ativar: até isso, a API só libera o perfil, a troca de senha, a ativação do 2FA e a saída. */
    public function precisaConfigurarDoisFatores(): bool
    {
        return $this->exigeDoisFatores() && ! $this->doisFatoresAtivo();
    }

    protected function twoFactorAtivo(): Attribute
    {
        return Attribute::get(fn () => $this->doisFatoresAtivo());
    }

    protected function twoFactorObrigatorio(): Attribute
    {
        return Attribute::get(fn () => $this->exigeDoisFatores());
    }

    /** Quantos códigos de recuperação ainda não foram usados (a tela avisa quando estão acabando). */
    protected function twoFactorCodigosRestantes(): Attribute
    {
        return Attribute::get(fn () => $this->doisFatoresAtivo() ? count($this->two_factor_recovery_codes ?? []) : 0);
    }

    public function isAdministradorInterno(): bool
    {
        return $this->role === UserRole::AdministradorInterno;
    }

    /**
     * Grava de qual prefeitura é a alteração: o administrador da prefeitura consulta e exporta só a
     * auditoria dela, e o filtro precisa ser por uma coluna, não por adivinhação a partir do tipo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        $data['tenant_id'] = $this->tenant_id;

        return $data;
    }
}
