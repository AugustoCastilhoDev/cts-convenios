<?php

namespace App\Providers;

use App\Policies\AuditPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use OwenIt\Auditing\Models\Audit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O model Audit é do pacote (não dá para usar #[UsePolicy] nele).
        Gate::policy(Audit::class, AuditPolicy::class);

        $this->definirRegraDeSenha();
        $this->definirLimitesDeRequisicoes();
    }

    /**
     * Regra única de senha (usuário novo, troca, link do e-mail): 10+ caracteres com letras e
     * números. Em produção também recusa senhas que já vazaram em algum incidente (consulta a base
     * pública Have I Been Pwned enviando só 5 caracteres de um hash, nunca a senha).
     */
    private function definirRegraDeSenha(): void
    {
        Password::defaults(fn () => Password::min(10)
            ->letters()
            ->numbers()
            ->when(app()->isProduction(), fn (Password $regra) => $regra->uncompromised()));
    }

    private function definirLimitesDeRequisicoes(): void
    {
        // Login: 5 tentativas por minuto para cada e-mail em cada IP. Barra tentativa de
        // senha por força bruta sem trancar a conta inteira (o que serviria de ataque).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn () => response()->json([
                'message' => 'Muitas tentativas de login. Aguarde um minuto e tente novamente.',
            ], 429)));

        // "Esqueci minha senha": barra quem tenta encher a caixa de e-mail de alguém (por e-mail) e
        // quem varre e-mails de um mesmo IP. A resposta é igual para e-mail existente ou não.
        RateLimiter::for('esqueci-senha', fn (Request $request) => [
            Limit::perMinute(3)->by('ip:'.$request->ip()),
            Limit::perHour(5)->by('email:'.mb_strtolower((string) $request->input('email'))),
        ]);

        // Uso do link: o token tem 60 min e uso único, mas ainda assim não se deixa adivinhar em massa.
        RateLimiter::for('redefinir-senha', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Uso normal da API: folgado para a tela (o sino consulta a cada minuto), mas
        // impede que um token vazado seja usado para varrer os dados em massa.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)
            ->by($request->user()?->id ?: $request->ip()));

        // Formulário público da landing page: poucos envios por hora por IP.
        RateLimiter::for('contato', fn (Request $request) => Limit::perHour(config('contato.limite_por_hora'))->by($request->ip()));
    }
}
