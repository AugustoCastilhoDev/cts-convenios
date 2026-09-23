<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quem já leu cada alerta no sino do sistema. É por usuário: o alerta é da
     * prefeitura, mas "lido" é individual (o Fiscal pode não ter visto o que o
     * Gestor já leu).
     */
    public function up(): void
    {
        Schema::create('alerta_prazo_leituras', function (Blueprint $table) {
            $table->id();
            // users.id continua bigint; alertas_prazo.id é uuid.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('alerta_prazo_id')->constrained('alertas_prazo')->cascadeOnDelete();
            $table->timestamp('lido_em');

            $table->unique(['user_id', 'alerta_prazo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_prazo_leituras');
    }
};
