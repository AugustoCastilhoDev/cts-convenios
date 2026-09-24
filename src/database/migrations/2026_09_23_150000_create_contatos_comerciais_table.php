<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedidos de contato/demonstração vindos do formulário da landing page. Não
     * pertencem a nenhuma prefeitura (ainda não são clientes): sem tenant_id.
     */
    public function up(): void
    {
        Schema::create('contatos_comerciais', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->string('cargo')->nullable();
            $table->string('municipio');
            $table->string('email');
            $table->string('telefone', 40)->nullable();
            $table->text('mensagem')->nullable();

            // LGPD: guardamos quando a pessoa aceitou o uso dos dados para retorno do contato.
            $table->timestamp('aceite_em');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contatos_comerciais');
    }
};
