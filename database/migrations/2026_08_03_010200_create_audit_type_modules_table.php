<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_type_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_type_version_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('module_type')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->json('conditional_logic')->nullable();
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->timestamps();

            $table->unique(['audit_type_version_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_type_modules');
    }
};
