<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ManageAudits;
use App\Models\Audit;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
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

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'audyt';

    protected static ?string $pluralModelLabel = 'audyty';

    protected static ?string $navigationLabel = 'Audyty';

    protected static string|UnitEnum|null $navigationGroup = 'Audyty';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podstawowe dane audytu')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->label('Tytul')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('client_id')
                                ->label('Klient')
                                ->relationship('client', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('client_location_id')
                                ->label('Lokalizacja')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('audit_template_id')
                                ->label('Szablon audytu')
                                ->relationship('template', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('status')
                                ->label('Status')
                                ->options(Audit::STATUSES)
                                ->default('draft')
                                ->required(),
                            DateTimePicker::make('scheduled_at')
                                ->label('Termin audytu')
                                ->seconds(false),
                            Select::make('lead_reviewer_id')
                                ->label('Lider techniczny')
                                ->relationship('leadReviewer', 'name')
                                ->searchable()
                                ->preload(),
                            Textarea::make('description')
                                ->label('Opis i cel audytu')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Wybrane moduly')
                    ->schema([
                        Repeater::make('selectedModules')
                            ->label('Moduly')
                            ->relationship()
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
                Section::make('Przypisani audytorzy')
                    ->schema([
                        Repeater::make('assignees')
                            ->label('Osoby')
                            ->relationship()
                            ->schema([
                                Select::make('user_id')
                                    ->label('Uzytkownik')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Select::make('role_in_audit')
                                    ->label('Rola w audycie')
                                    ->options([
                                        'auditor' => 'Audytor',
                                        'lead' => 'Lider',
                                        'observer' => 'Obserwator',
                                    ])
                                    ->default('auditor')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Tytul')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label('Lokalizacja')
                    ->sortable(),
                TextColumn::make('template.name')
                    ->label('Szablon')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Audit::STATUSES[$state] ?? $state),
                TextColumn::make('selected_modules_count')
                    ->label('Moduly')
                    ->counts('selectedModules')
                    ->sortable(),
                TextColumn::make('assignees_count')
                    ->label('Osoby')
                    ->counts('assignees')
                    ->sortable(),
                TextColumn::make('scheduled_at')
                    ->label('Termin')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Audit::STATUSES),
                SelectFilter::make('client')
                    ->label('Klient')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('template')
                    ->label('Szablon')
                    ->relationship('template', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => ManageAudits::route('/'),
        ];
    }
}
