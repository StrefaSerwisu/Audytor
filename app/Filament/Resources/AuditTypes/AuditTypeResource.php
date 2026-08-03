<?php

namespace App\Filament\Resources\AuditTypes;

use App\Filament\Resources\AuditTypes\Pages\ManageAuditTypes;
use App\Models\AuditType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditTypeResource extends Resource
{
    protected static ?string $model = AuditType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    protected static ?string $modelLabel = 'typ audytu';

    protected static ?string $pluralModelLabel = 'typy audytow';

    protected static ?string $navigationLabel = 'Typy audytow';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytow';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Produkt audytowy')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->label('Nazwa')->required()->maxLength(255),
                    TextInput::make('code')->label('Kod')->required()->unique(ignoreRecord: true)->maxLength(100),
                    TextInput::make('category')->label('Kategoria')->maxLength(255),
                    Toggle::make('active')->label('Aktywny')->default(true),
                    Textarea::make('description')->label('Opis')->rows(4)->columnSpanFull(),
                    Textarea::make('sales_instructions')->label('Instrukcje dla Sales')->rows(5)->columnSpanFull(),
                    Textarea::make('delivery_instructions')->label('Instrukcje realizacji')->rows(5)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
            TextColumn::make('code')->label('Kod')->badge()->searchable(),
            TextColumn::make('category')->label('Kategoria')->placeholder('-')->sortable(),
            TextColumn::make('currentVersion.version')->label('Aktualna wersja')->prefix('v')->placeholder('-'),
            TextColumn::make('versions_count')->label('Wersje')->counts('versions'),
            IconColumn::make('active')->label('Aktywny')->boolean(),
        ])->filters([
            TernaryFilter::make('active')->label('Aktywny'),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditTypes::route('/')];
    }
}
