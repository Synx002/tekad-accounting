<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('type', 32);
            $table->decimal('quantity_change', 18, 2);
            $table->decimal('quantity_after', 18, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
