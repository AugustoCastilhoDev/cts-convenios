<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verificação em duas etapas (app autenticador, TOTP). O segredo e os códigos de recuperação ficam
     * criptografados (cast "encrypted" do Model, chave APP_KEY): um vazamento só do banco não os entrega.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Segredo gerado na ativação; só vale depois de two_factor_confirmed_at (a pessoa provou ter o app).
            $table->text('two_factor_secret')->nullable()->after('senha_temporaria_expira_em');
            // Códigos de uso único (guardados só como hash), para quem perder o celular.
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            // Último período de 30 s já aceito: o mesmo código não entra duas vezes (repetição).
            $table->unsignedBigInteger('two_factor_ultimo_passo')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_ultimo_passo']);
        });
    }
};
