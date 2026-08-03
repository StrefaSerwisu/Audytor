<?php

namespace App\Filament\Resources\SalesQualificationQuestions;

use App\Filament\Resources\SalesQualificationQuestions\Pages\ManageSalesQualificationQuestions;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
use App\Models\SalesQualificationQuestion;
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

class SalesQualificationQuestionResource extends Resource
{
    protected static ?string $model = SalesQualificationQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Pytania kwalifikacyjne';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytow';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pytanie kwalifikacyjne Sales')->schema([
                Grid::make(2)->schema([
                    Select::make('audit_type_module_id')->label('Modul Sales')->relationship(
                        'module',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('module_type', AuditTypeModule::TYPE_SALES)
                            ->whereHas('version', fn (Builder $version): Builder => $version->where('status', AuditTypeVersion::STATUS_DRAFT)),
                    )->searchable()->preload()->required(),
                    TextInput::make('code')->label('Kod')->required()->maxLength(100),
                    Select::make('field_type')->label('Typ pola')->options(SalesQualificationQuestion::FIELD_TYPES)->required(),
                    TextInput::make('sort_order')->label('Kolejnosc')->numeric()->minValue(0)->default(0)->required(),
                    Textarea::make('question')->label('Pytanie')->required()->rows(3)->columnSpanFull(),
                    Textarea::make('description')->label('Opis')->rows(3)->columnSpanFull(),
                    Textarea::make('helper_text')->label('Podpowiedz dla Sales')->rows(2)->columnSpanFull(),
                    Toggle::make('required')->label('Wymagane')->default(false),
                    Toggle::make('affects_scope')->label('Wplywa na zakres')->default(false),
                    Toggle::make('affects_pricing')->label('Wplywa na wycene')->default(false),
                    Toggle::make('active')->label('Aktywne')->default(true),
                    TextInput::make('pricing_variable')->label('Zmienna wyceny')->maxLength(255),
                    KeyValue::make('options_json')->label('Opcje odpowiedzi')->columnSpanFull(),
                    KeyValue::make('conditional_logic')->label('Logika warunkowa')->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('module.version.auditType.name')->label('Typ audytu')->searchable(),
            TextColumn::make('module.name')->label('Modul Sales')->searchable(),
            TextColumn::make('sort_order')->label('#')->sortable(),
            TextColumn::make('code')->label('Kod')->badge(),
            TextColumn::make('question')->label('Pytanie')->limit(70)->searchable(),
            TextColumn::make('field_type')->label('Typ')->badge()->formatStateUsing(fn (string $state): string => SalesQualificationQuestion::FIELD_TYPES[$state] ?? $state),
            IconColumn::make('affects_scope')->label('Zakres')->boolean(),
            IconColumn::make('affects_pricing')->label('Wycena')->boolean(),
            IconColumn::make('active')->label('Aktywne')->boolean(),
        ])->filters([
            SelectFilter::make('field_type')->label('Typ pola')->options(SalesQualificationQuestion::FIELD_TYPES),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSalesQualificationQuestions::route('/')];
    }
}
