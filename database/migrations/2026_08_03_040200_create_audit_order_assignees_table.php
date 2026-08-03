<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_order_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('assignment_role');
            $table->decimal('planned_hours', 12, 2)->nullable();
            $table->string('competency_level')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['audit_order_id', 'user_id', 'assignment_role']);
            $table->index(['user_id', 'assignment_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_order_assignees');
    }
};
