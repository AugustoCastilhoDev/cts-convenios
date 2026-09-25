<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validade da senha temporária: até quando quem recebeu a senha gerada pelo sistema ainda pode
     * entrar com ela. Só faz sentido enquanto must_change_password está ligado.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('senha_temporaria_expira_em')->nullable()->after('must_change_password');
        });

        // Quem já estava com senha temporária ganha o prazo cheio a partir de agora (não expira de surpresa).
        DB::table('users')
            ->where('must_change_password', true)
            ->update(['senha_temporaria_expira_em' => now()->addDays(max(1, (int) config('seguranca.senha_temporaria_dias', 7)))]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('senha_temporaria_expira_em');
        });
    }
};
