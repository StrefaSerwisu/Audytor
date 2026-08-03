<?php

namespace Database\Seeders;

use App\Models\AuditType;
use App\Models\AuditTypeModule;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoPricingRulesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();
        $type = AuditType::firstOrCreate(
            ['code' => 'AUD-DEMO-2C'],
            [
                'name' => 'Audyt demonstracyjny 2C', 'category' => 'demo',
                'description' => 'Demonstracyjny typ audytu do testowania silnika wyceny. Stawki nie sa oferta Global IT.',
                'active' => true, 'created_by' => $admin->id, 'updated_by' => $admin->id,
            ],
        );

        if ($type->currentVersion()->exists()) {
            return;
        }

        $version = $type->createDraftVersion([
            'default_hourly_rate' => '250.00', 'minimum_hours' => '12.00',
            'minimum_price' => '0.00', 'reserve_percent' => '15.00',
            'default_engineers_count' => 1, 'default_tax_rate' => '23.00', 'default_validity_days' => 14,
            'sales_instructions' => 'DEMO: uzupelnij liczby zasobow klienta.',
        ]);
        $salesModule = $version->modules()->create([
            'name' => 'Zakres demonstracyjny Sales', 'code' => 'DEMO_SALES',
            'module_type' => AuditTypeModule::TYPE_SALES, 'sort_order' => 10, 'active' => true,
        ]);
        $version->modules()->create([
            'name' => 'Realizacja demonstracyjna', 'code' => 'DEMO_TECH',
            'module_type' => AuditTypeModule::TYPE_TECHNICAL, 'sort_order' => 20, 'active' => true,
        ]);

        foreach ([
            ['code' => 'users_count', 'question' => 'Liczba uzytkownikow', 'pricing_variable' => 'users_count'],
            ['code' => 'computers_count', 'question' => 'Liczba komputerow', 'pricing_variable' => 'computers_count'],
            ['code' => 'servers_count', 'question' => 'Liczba serwerow', 'pricing_variable' => 'servers_count'],
        ] as $index => $question) {
            $salesModule->salesQuestions()->create([
                ...$question, 'field_type' => 'number', 'required' => true, 'affects_scope' => true,
                'affects_pricing' => true, 'active' => true, 'sort_order' => ($index + 1) * 10,
            ]);
        }

        foreach ([
            ['code' => 'base', 'name' => 'Przygotowanie bazowe', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '4.00', 'category' => 'base'],
            ['code' => 'users', 'name' => 'Uzytkownicy', 'calculation_type' => 'hours_per_quantity', 'quantity_source' => 'answer:users_count', 'hours_per_unit' => '0.15', 'category' => 'users'],
            ['code' => 'computers', 'name' => 'Komputery', 'calculation_type' => 'hours_per_quantity', 'quantity_source' => 'answer:computers_count', 'hours_per_unit' => '0.10', 'category' => 'computers'],
            ['code' => 'servers', 'name' => 'Serwery', 'calculation_type' => 'hours_per_quantity', 'quantity_source' => 'answer:servers_count', 'hours_per_unit' => '1.50', 'category' => 'servers'],
            ['code' => 'locations', 'name' => 'Lokalizacje', 'calculation_type' => 'hours_per_quantity', 'quantity_source' => 'locations_count', 'hours_per_unit' => '2.00', 'category' => 'locations'],
            ['code' => 'reporting', 'name' => 'Przygotowanie raportu', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '3.00', 'category' => 'reporting'],
            ['code' => 'review', 'name' => 'Weryfikacja lidera', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '2.00', 'category' => 'technical_review'],
        ] as $index => $rule) {
            $version->pricingRules()->create([
                ...$rule, 'description' => 'DEMO - wartosc nie jest finalna stawka Global IT.',
                'active' => true, 'rule_type' => 'always', 'sort_order' => ($index + 1) * 10,
            ]);
        }

        $version->publish($admin);
    }
}
