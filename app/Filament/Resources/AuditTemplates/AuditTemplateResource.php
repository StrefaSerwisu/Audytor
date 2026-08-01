<?php

namespace App\Filament\Resources\AuditTemplates;

use App\Filament\Resources\AuditTemplates\Pages\ManageAuditTemplates;
use App\Models\AuditTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditTemplateResource extends Resource
{
    protected static ?string $model = AuditTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'szablon audytu';

    protected static ?string $pluralModelLabel = 'szablony audytow';

    protected static ?string $navigationLabel = 'Szablony audytow';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytu';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Szablon audytu')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nazwa')
                                ->required()
                                ->maxLength(255),
                            Toggle::make('active')
                                ->label('Aktywny')
                                ->default(true),
                            Textarea::make('description')
                                ->label('Opis')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Moduly w szablonie')
                    ->schema([
                        Repeater::make('templateModules')
                            ->label('Moduly')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->schema([
                                Select::make('audit_module_id')
                                    ->label('Modul')
                                    ->relationship('module', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('sort_order')
                                    ->label('Kolejnosc')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktywny')
                    ->boolean(),
                TextColumn::make('template_modules_count')
                    ->label('Moduly')
                    ->counts('templateModules')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Aktualizacja')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Aktywny'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditTemplates::route('/'),
        ];
    }
}
