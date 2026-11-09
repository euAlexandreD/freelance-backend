<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->string('titulo', 120)->nullable()->after('categoria_servico_id');
            $table->enum('tipo_preco', ['fixo', 'a_partir_de', 'por_hora', 'a_combinar'])
                ->default('a_combinar')
                ->after('descricao');
            $table->decimal('preco', 10, 2)->nullable()->after('tipo_preco');
        });

        // Serviços antigos ganham um título provisório tirado da descrição.
        DB::table('servicos')->whereNull('titulo')->orderBy('id')->each(function ($servico) {
            DB::table('servicos')->where('id', $servico->id)->update([
                'titulo' => mb_strimwidth($servico->descricao, 0, 60, '...'),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn(['titulo', 'tipo_preco', 'preco']);
        });
    }
};
