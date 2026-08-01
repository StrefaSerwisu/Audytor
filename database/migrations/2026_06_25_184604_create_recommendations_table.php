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
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('technical_description')->nullable();
            $table->text('business_description')->nullable();
            $table->text('recommendation_text');
            $table->string('risk_level')->nullable()->index();
            $table->string('priority')->nullable()->index();
            $table->string('suggested_deadline')->nullable();
            $table->unsignedInteger('estimated_hours_min')->nullable();
            $table->unsignedInteger('estimated_hours_max')->nullable();
            $table->boolean('global_it_can_do')->default(true)->index();
            $table->string('sales_category')->nullable()->index();
            $table->jsonb('tags_json')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
