<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Os Models de domínio usam UUID, mas `users` mantém id bigint (decisão
     * registrada no ROADMAP). auditable_id precisa aceitar os dois formatos,
     * senão auditar um User estoura no Postgres ("invalid input syntax for
     * type uuid"). O SQLite dos testes não valida o tipo e não pega isso.
     */
    public function up(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $tabela = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->table($tabela, function (Blueprint $table) {
            $table->string('auditable_id', 36)->change();
        });
    }

    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $tabela = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->table($tabela, function (Blueprint $table) {
            $table->uuid('auditable_id')->change();
        });
    }
};
