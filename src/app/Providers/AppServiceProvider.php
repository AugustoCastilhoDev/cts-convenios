<?php

namespace App\Providers;

use App\Policies\AuditPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
