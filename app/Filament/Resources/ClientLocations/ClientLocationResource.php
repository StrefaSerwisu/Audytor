<?php

namespace App\Filament\Resources\ClientLocations;

use App\Filament\Resources\ClientLocations\Pages\ManageClientLocations;
use App\Models\ClientLocation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ClientLocationResource extends Resource
{
    protected static ?string $model = ClientLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'lokalizacja';

    protected static ?string $pluralModelLabel = 'lokalizacje';

    protected static ?string $navigationLabel = 'Lokalizacje';

    protected static string|UnitEnum|null $navigationGroup = 'Klienci';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lokalizacja klienta')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('client_id')
                                ->label('Klient')
                                ->relationship('client', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            TextInput::make('name')
                                ->label('Nazwa lokalizacji')
                                ->required()
                                ->maxLength(255),
                            Select::make('location_type')
                                ->label('Typ lokalizacji')
                                ->options([
                                    'office' => 'Biuro',
                                    'warehouse' => 'Magazyn',
                                    'production' => 'Produkcja',
                                    'server_room' => 'Serwerownia',
                                    'branch' => 'Oddział',
                                ]),
                            Textarea::make('address')
                                ->label('Adres')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Kontakt lokalny')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('contact_name')
                                ->label('Osoba kontaktowa')
                                ->maxLength(255),
                            TextInput::make('contact_email')
                                ->label('E-mail')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('contact_phone')
                                ->label('Telefon')
                                ->tel()
                                ->maxLength(64),
                            Textarea::make('notes')
                                ->label('Opis / notatki')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Lokalizacja')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'office' => 'Biuro',
                        'warehouse' => 'Magazyn',
                        'production' => 'Produkcja',
                        'server_room' => 'Serwerownia',
                        'branch' => 'Oddział',
                        default => $state ?? '-',
                    }),
                TextColumn::make('contact_name')
                    ->label('Kontakt')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Aktualizacja')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->label('Klient')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('location_type')
                    ->label('Typ')
                    ->options([
                        'office' => 'Biuro',
                        'warehouse' => 'Magazyn',
                        'production' => 'Produkcja',
                        'server_room' => 'Serwerownia',
                        'branch' => 'Oddział',
                    ]),
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
            'index' => ManageClientLocations::route('/'),
        ];
    }
}
