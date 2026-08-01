<?php

namespace App\Filament\Resources\AuditModules;

use App\Filament\Resources\AuditModules\Pages\ManageAuditModules;
use App\Models\AuditModule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditModuleResource extends Resource
{
    protected static ?string $model = AuditModule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $modelLabel = 'modul audytowy';

    protected static ?string $pluralModelLabel = 'moduly audytowe';

    protected static ?string $navigationLabel = 'Moduly audytowe';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytu';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Modul audytowy')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nazwa')
                                ->required()
                                ->maxLength(255),
                            Select::make('category')
                                ->label('Kategoria')
                                ->options([
                                    'network' => 'Siec',
                                    'security' => 'Bezpieczenstwo',
                                    'servers' => 'Serwery',
                                    'cloud' => 'Chmura',
                                    'workstations' => 'Komputery',
                                    'documentation' => 'Dokumentacja',
                                    'facility' => 'Pomieszczenia',
                                ])
                                ->searchable(),
                            TextInput::make('sort_order')
                                ->label('Kolejnosc')
                                ->numeric()
                                ->default(0)
                                ->required(),
                            Toggle::make('active')
                                ->label('Aktywny')
                                ->default(true),
                            Textarea::make('description')
                                ->label('Opis')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategoria')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'network' => 'Siec',
                        'security' => 'Bezpieczenstwo',
                        'servers' => 'Serwery',
                        'cloud' => 'Chmura',
                        'workstations' => 'Komputery',
                        'documentation' => 'Dokumentacja',
                        'facility' => 'Pomieszczenia',
                        default => $state ?? '-',
                    }),
                IconColumn::make('active')
                    ->label('Aktywny')
                    ->boolean(),
                TextColumn::make('templates_count')
                    ->label('Szablony')
                    ->counts('templates')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Aktualizacja')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategoria')
                    ->options([
                        'network' => 'Siec',
                        'security' => 'Bezpieczenstwo',
                        'servers' => 'Serwery',
                        'cloud' => 'Chmura',
                        'workstations' => 'Komputery',
                        'documentation' => 'Dokumentacja',
                        'facility' => 'Pomieszczenia',
                    ]),
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
            'index' => ManageAuditModules::route('/'),
        ];
    }
}
