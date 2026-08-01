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
        Schema::create('audit_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_module_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('instruction')->nullable();
            $table->string('field_type');
            $table->boolean('is_required')->default(false)->index();
            $table->boolean('allow_not_applicable')->default(true);
            $table->boolean('require_comment_when_na')->default(false);
            $table->boolean('require_photo')->default(false)->index();
            $table->boolean('require_screenshot')->default(false)->index();
            $table->boolean('risk_enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('config_json')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_questions');
    }
};
