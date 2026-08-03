<?php

namespace App\Filament\Resources\PricingRules;

use App\Filament\Resources\PricingRules\Pages\ManagePricingRules;
use App\Models\AuditTypeVersion;
use App\Models\PricingRule;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Reguly wyceny';

    protected static ?string $modelLabel = 'regula wyceny';

    protected static ?string $pluralModelLabel = 'reguly wyceny';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytow';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Regula')->schema([
                Grid::make(2)->schema([
                    Select::make('audit_type_version_id')->label('Wersja robocza')->relationship(
                        'version',
                        'name_snapshot',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', AuditTypeVersion::STATUS_DRAFT),
                    )->searchable()->preload()->required(),
                    TextInput::make('code')->label('Kod')->required()->maxLength(100),
                    TextInput::make('name')->label('Nazwa')->required()->maxLength(255),
                    Select::make('category')->label('Kategoria')->options(PricingRule::CATEGORIES)->required(),
                    Textarea::make('description')->label('Opis')->rows(2)->columnSpanFull(),
                    Toggle::make('active')->label('Aktywna')->default(true),
                    TextInput::make('sort_order')->label('Kolejnosc')->numeric()->minValue(0)->default(0)->required(),
                ]),
            ]),
            Section::make('Warunek')->schema([
                Grid::make(2)->schema([
                    Select::make('rule_type')->label('Typ warunku')->options(PricingRule::RULE_TYPES)->required()->default('always'),
                    TextInput::make('source_question_code')->label('Kod pytania lub modulu')->maxLength(100),
                    TextInput::make('operator')->label('Operator')->maxLength(50),
                    KeyValue::make('comparison_value')->label('Wartosc porownania')->columnSpanFull(),
                ]),
            ]),
            Section::make('Kalkulacja')->schema([
                Grid::make(3)->schema([
                    Select::make('calculation_type')->label('Rodzaj kalkulacji')->options(PricingRule::CALCULATION_TYPES)->required(),
                    TextInput::make('quantity_source')->label('Zrodlo ilosci')->helperText('answer:kod, fixed, locations_count lub active_sales_modules_count'),
                    TextInput::make('fixed_quantity')->label('Stala ilosc')->numeric()->minValue(0),
                    TextInput::make('hours_per_unit')->label('Godzin / jednostke')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('fixed_hours')->label('Stale godziny')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('unit_price')->label('Cena / jednostke')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('fixed_price')->label('Stala cena')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('minimum_value')->label('Minimum ilosci')->numeric()->minValue(0),
                    TextInput::make('maximum_value')->label('Maksimum ilosci')->numeric()->minValue(0),
                    KeyValue::make('configuration')->label('Konfiguracja dodatkowa')->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('version.auditType.name')->label('Typ audytu')->searchable(),
            TextColumn::make('version.version')->label('Wersja')->prefix('v'),
            TextColumn::make('sort_order')->label('#')->sortable(),
            TextColumn::make('code')->label('Kod')->badge()->searchable(),
            TextColumn::make('name')->label('Nazwa')->searchable(),
            TextColumn::make('calculation_type')->label('Kalkulacja')->badge()->formatStateUsing(fn (string $state): string => PricingRule::CALCULATION_TYPES[$state] ?? $state),
            TextColumn::make('category')->label('Kategoria')->formatStateUsing(fn (string $state): string => PricingRule::CATEGORIES[$state] ?? $state),
            IconColumn::make('active')->label('Aktywna')->boolean(),
        ])->filters([
            SelectFilter::make('audit_type_version_id')->label('Wersja')->relationship('version', 'name_snapshot'),
            SelectFilter::make('calculation_type')->label('Kalkulacja')->options(PricingRule::CALCULATION_TYPES),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePricingRules::route('/')];
    }
}
