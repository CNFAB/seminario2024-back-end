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
        Schema::create('pagos', function (Blueprint $table) {
           $table->id();
        $table->foreignId('id_multiple')
              ->constrained('reparacion_multiple')
              ->onDelete('cascade');
        $table->string('mp_preference_id')->nullable();
        $table->string('mp_payment_id')->nullable();
        $table->enum('estado', ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'EN_PROCESO'])
              ->default('PENDIENTE');
        $table->decimal('monto', 10, 2);
        $table->string('descripcion')->nullable();
        $table->timestamp('fecha_pago')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
