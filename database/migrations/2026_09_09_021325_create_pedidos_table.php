<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_cliente_id')->constrained('perfil_clientes')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->restrictOnDelete();
            $table->enum('status', ['pendente', 'aceito', 'recusado', 'concluido', 'cancelado'])->default('pendente');
            $table->text('descricao_pedido')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
