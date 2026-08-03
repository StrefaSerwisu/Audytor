<?php

namespace App\Filament\Resources\AuditControlDefinitions;

use App\Enums\CompetencyLevel;
use App\Filament\Resources\AuditControlDefinitions\Pages\ManageAuditControlDefinitions;
use App\Models\AuditControlDefinition;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
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

class AuditControlDefinitionResource extends Resource
{
    protected static ?string $model = AuditControlDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Kontrole techniczne';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytow';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kontrola techniczna')->schema([
                Grid::make(2)->schema([
                    Select::make('audit_type_module_id')->label('Modul techniczny')->relationship(
                        'module',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('module_type', AuditTypeModule::TYPE_TECHNICAL)
                            ->whereHas('version', fn (Builder $version): Builder => $version->where('status', AuditTypeVersion::STATUS_DRAFT)),
                    )->searchable()->preload()->required(),
                    TextInput::make('code')->label('Kod')->required()->maxLength(100),
                    TextInput::make('name')->label('Nazwa kontroli')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('objective')->label('Cel')->rows(2)->columnSpanFull(),
                    Textarea::make('description')->label('Opis')->rows(3)->columnSpanFull(),
                    Textarea::make('execution_instructions')->label('Instrukcja krok po kroku')->rows(7)->columnSpanFull(),
                    Textarea::make('where_to_check')->label('Gdzie sprawdzic')->rows(3)->columnSpanFull(),
                    Textarea::make('required_access')->label('Wymagany dostep')->rows(3),
                    Textarea::make('required_tools')->label('Wymagane narzedzia')->rows(3),
                    Select::make('minimum_competency_level')->label('Minimalna kompetencja')->options(CompetencyLevel::options()),
                    TextInput::make('estimated_minutes')->label('Szacowany czas')->numeric()->minValue(0)->default(0)->required(),
                    Select::make('field_type')->label('Typ odpowiedzi')->options(AuditControlDefinition::FIELD_TYPES)->required(),
                    TextInput::make('sort_order')->label('Kolejnosc')->numeric()->minValue(0)->default(0)->required(),
                    Toggle::make('required')->label('Wymagana')->default(false),
                    Toggle::make('allow_not_applicable')->label('Pozwol N/D')->default(true),
                    Toggle::make('require_comment_when_na')->label('Komentarz przy N/D')->default(false),
                    Toggle::make('require_evidence')->label('Wymagaj dowodu')->default(false),
                    Toggle::make('active')->label('Aktywna')->default(true),
                    KeyValue::make('options_json')->label('Opcje odpowiedzi')->columnSpanFull(),
                    KeyValue::make('evidence_types')->label('Typy dowodow')->columnSpanFull(),
                    Textarea::make('positive_criteria')->label('Kryteria pozytywne')->rows(3),
                    Textarea::make('negative_criteria')->label('Kryteria negatywne')->rows(3),
                    Textarea::make('escalation_criteria')->label('Kryteria eskalacji')->rows(3)->columnSpanFull(),
                    Select::make('default_risk_level')->label('Domyslne ryzyko')->options([
                        'low' => 'Niskie', 'medium' => 'Srednie', 'high' => 'Wysokie', 'critical' => 'Krytyczne',
                    ]),
                    TextInput::make('standard_reference')->label('Odniesienie do standardu')->maxLength(255),
                    Textarea::make('default_recommendation')->label('Domyslna rekomendacja')->rows(4)->columnSpanFull(),
                    KeyValue::make('conditional_logic')->label('Logika warunkowa')->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('module.version.auditType.name')->label('Typ audytu')->searchable(),
            TextColumn::make('module.name')->label('Modul techniczny')->searchable(),
            TextColumn::make('sort_order')->label('#')->sortable(),
            TextColumn::make('code')->label('Kod')->badge(),
            TextColumn::make('name')->label('Kontrola')->limit(60)->searchable(),
            TextColumn::make('minimum_competency_level')->label('Kompetencja')->formatStateUsing(fn (?CompetencyLevel $state): string => $state?->label() ?? '-'),
            TextColumn::make('estimated_minutes')->label('Minuty')->suffix(' min'),
            IconColumn::make('require_evidence')->label('Dowod')->boolean(),
            IconColumn::make('active')->label('Aktywna')->boolean(),
        ])->filters([
            SelectFilter::make('field_type')->label('Typ odpowiedzi')->options(AuditControlDefinition::FIELD_TYPES),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditControlDefinitions::route('/')];
    }
}
