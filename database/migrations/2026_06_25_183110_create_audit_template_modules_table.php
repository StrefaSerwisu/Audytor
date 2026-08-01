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
        Schema::create('audit_template_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_module_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unique(['audit_template_id', 'audit_module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_template_modules');
    }
};
