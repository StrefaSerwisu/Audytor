<?php

namespace App\Filament\Resources\AuditTypeModules;

use App\Filament\Resources\AuditTypeModules\Pages\ManageAuditTypeModules;
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

class AuditTypeModuleResource extends Resource
{
    protected static ?string $model = AuditTypeModule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Moduly wersji';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytow';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Modul wersji audytu')->schema([
                Grid::make(2)->schema([
                    Select::make('audit_type_version_id')->label('Wersja robocza')->relationship(
                        'version',
                        'name_snapshot',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', AuditTypeVersion::STATUS_DRAFT),
                    )->searchable()->preload()->required(),
                    Select::make('module_type')->label('Sciezka')->options(AuditTypeModule::TYPES)->required(),
                    TextInput::make('name')->label('Nazwa')->required()->maxLength(255),
                    TextInput::make('code')->label('Kod')->required()->maxLength(100),
                    TextInput::make('sort_order')->label('Kolejnosc')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('estimated_minutes')->label('Szacowany czas')->numeric()->minValue(0)->default(0)->required(),
                    Toggle::make('active')->label('Aktywny')->default(true),
                    Textarea::make('description')->label('Opis')->rows(3)->columnSpanFull(),
                    KeyValue::make('conditional_logic')->label('Logika warunkowa')->columnSpanFull(),
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
            TextColumn::make('name')->label('Modul')->searchable(),
            TextColumn::make('code')->label('Kod')->badge(),
            TextColumn::make('module_type')->label('Sciezka')->badge()->formatStateUsing(fn (string $state): string => AuditTypeModule::TYPES[$state] ?? $state),
            TextColumn::make('estimated_minutes')->label('Minuty')->suffix(' min'),
            IconColumn::make('active')->label('Aktywny')->boolean(),
        ])->filters([
            SelectFilter::make('module_type')->label('Sciezka')->options(AuditTypeModule::TYPES),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditTypeModules::route('/')];
    }
}
