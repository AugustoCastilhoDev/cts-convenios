<?php

namespace App\Providers;

use App\Policies\AuditPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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

        $this->definirLimitesDeRequisicoes();
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

        // Uso normal da API: folgado para a tela (o sino consulta a cada minuto), mas
        // impede que um token vazado seja usado para varrer os dados em massa.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)
            ->by($request->user()?->id ?: $request->ip()));

        // Formulário público da landing page: poucos envios por hora por IP.
        RateLimiter::for('contato', fn (Request $request) => Limit::perHour(config('contato.limite_por_hora'))->by($request->ip()));
    }
}
