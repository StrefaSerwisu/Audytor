<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualification_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_qualification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_qualification_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_code');
            $table->json('question_snapshot');
            $table->json('value_json')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_qualification_id', 'question_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualification_answers');
    }
};
