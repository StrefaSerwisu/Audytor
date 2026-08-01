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
        Schema::create('audit_follow_up_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_publication_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_key', 120)->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 40)->nullable()->index();
            $table->string('status', 40)->default('new')->index();
            $table->date('due_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('client_visible')->default(true)->index();
            $table->timestamps();

            $table->unique(['audit_publication_id', 'source_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_follow_up_tasks');
    }
};
