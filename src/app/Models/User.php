<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Notifications\RedefinirSenha;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Attributes\UseResource;
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
 * password e remember_token ficam fora da trilha de auditoria (config/audit.php).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UsePolicy(UserPolicy::class)]
#[UseResource(UserResource::class)]
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

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
