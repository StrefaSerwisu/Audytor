<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_type_versions', function (Blueprint $table) {
            $table->decimal('default_hourly_rate', 12, 2)->nullable();
            $table->decimal('minimum_hours', 12, 2)->default(0);
            $table->decimal('minimum_price', 14, 2)->default(0);
            $table->decimal('reserve_percent', 7, 2)->default(0);
            $table->unsignedInteger('default_engineers_count')->default(1);
            $table->decimal('default_tax_rate', 7, 2)->default(23);
            $table->unsignedInteger('default_validity_days')->default(14);
        });
    }

    public function down(): void
    {
        Schema::table('audit_type_versions', function (Blueprint $table) {
            $table->dropColumn([
                'default_hourly_rate', 'minimum_hours', 'minimum_price', 'reserve_percent',
                'default_engineers_count', 'default_tax_rate', 'default_validity_days',
            ]);
        });
    }
};
