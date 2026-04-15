<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('acquisition_date');
            $table->decimal('cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->string('depreciation_method', 32)->default('straight_line');
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('book_value', 18, 2);
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
