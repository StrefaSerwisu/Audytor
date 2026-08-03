<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_type_version_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->string('rule_type')->default('always');
            $table->string('source_question_code')->nullable();
            $table->string('operator')->nullable();
            $table->json('comparison_value')->nullable();
            $table->string('calculation_type');
            $table->string('quantity_source')->nullable();
            $table->decimal('fixed_quantity', 14, 2)->nullable();
            $table->decimal('hours_per_unit', 12, 2)->default(0);
            $table->decimal('fixed_hours', 12, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('fixed_price', 14, 2)->default(0);
            $table->decimal('minimum_value', 14, 2)->nullable();
            $table->decimal('maximum_value', 14, 2)->nullable();
            $table->string('category');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['audit_type_version_id', 'code']);
            $table->index(['audit_type_version_id', 'active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
