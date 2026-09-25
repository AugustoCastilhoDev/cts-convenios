<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quando a equipe retornou o pedido. Nulo = ainda pendente.
     */
    public function up(): void
    {
        Schema::table('contatos_comerciais', function (Blueprint $table) {
            $table->timestamp('respondido_em')->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('contatos_comerciais', function (Blueprint $table) {
            $table->dropColumn('respondido_em');
        });
    }
};
