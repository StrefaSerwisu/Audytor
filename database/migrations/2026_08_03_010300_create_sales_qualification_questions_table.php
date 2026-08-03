<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_qualification_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_type_module_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->text('question');
            $table->text('description')->nullable();
            $table->string('field_type')->index();
            $table->json('options_json')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('conditional_logic')->nullable();
            $table->boolean('affects_scope')->default(false);
            $table->boolean('affects_pricing')->default(false);
            $table->string('pricing_variable')->nullable();
            $table->text('helper_text')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['audit_type_module_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_qualification_questions');
    }
};
