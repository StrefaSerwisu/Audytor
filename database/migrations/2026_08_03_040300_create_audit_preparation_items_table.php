<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_preparation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_order_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('required')->default(true);
            $table->boolean('completed')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['audit_order_id', 'code']);
            $table->index(['audit_order_id', 'status', 'required']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_preparation_items');
    }
};
