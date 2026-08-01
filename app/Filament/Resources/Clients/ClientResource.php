<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Client;
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

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $modelLabel = 'klient';

    protected static ?string $pluralModelLabel = 'klienci';

    protected static ?string $navigationLabel = 'Klienci';

    protected static string|UnitEnum|null $navigationGroup = 'Klienci';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dane klienta')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nazwa firmy')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('nip')
                                ->label('NIP')
                                ->maxLength(32),
                            Textarea::make('address')
                                ->label('Adres')
                                ->rows(3)
                                ->columnSpanFull(),
                            Select::make('account_manager_id')
                                ->label('Opiekun Global IT')
                                ->relationship('accountManager', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Aktywny',
                                    'inactive' => 'Nieaktywny',
                                    'prospect' => 'Prospekt',
                                ])
                                ->default('active')
                                ->required(),
                        ]),
                    ]),
                Section::make('Kontakt')
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
                                ->label('Notatki')
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
                TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktywny',
                        'inactive' => 'Nieaktywny',
                        'prospect' => 'Prospekt',
                        default => $state,
                    }),
                TextColumn::make('accountManager.name')
                    ->label('Opiekun')
                    ->sortable(),
                TextColumn::make('locations_count')
                    ->label('Lokalizacje')
                    ->counts('locations')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Aktualizacja')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktywny',
                        'inactive' => 'Nieaktywny',
                        'prospect' => 'Prospekt',
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
            'index' => ManageClients::route('/'),
        ];
    }
}
