<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->string('unit')->default('szt.');
            $table->decimal('unit_hours', 12, 2)->default(0);
            $table->decimal('total_hours', 12, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['quotation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
    }
};
