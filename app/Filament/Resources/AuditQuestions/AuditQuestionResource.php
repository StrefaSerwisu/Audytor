<?php

namespace App\Filament\Resources\AuditQuestions;

use App\Filament\Resources\AuditQuestions\Pages\ManageAuditQuestions;
use App\Models\AuditQuestion;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditQuestionResource extends Resource
{
    protected static ?string $model = AuditQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $modelLabel = 'pytanie audytowe';

    protected static ?string $pluralModelLabel = 'pytania audytowe';

    protected static ?string $navigationLabel = 'Pytania audytowe';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytu';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pytanie')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('audit_module_id')
                                ->label('Modul audytowy')
                                ->relationship('module', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('field_type')
                                ->label('Typ pola')
                                ->options(AuditQuestion::FIELD_TYPES)
                                ->searchable()
                                ->required(),
                            Textarea::make('question')
                                ->label('Tresc pytania')
                                ->required()
                                ->rows(3)
                                ->columnSpanFull(),
                            Textarea::make('instruction')
                                ->label('Instrukcja dla audytora')
                                ->rows(4)
                                ->columnSpanFull(),
                            TextInput::make('sort_order')
                                ->label('Kolejnosc')
                                ->numeric()
                                ->default(0)
                                ->required(),
                            Toggle::make('active')
                                ->label('Aktywne')
                                ->default(true),
                        ]),
                    ]),
                Section::make('Wymagania i walidacja')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_required')
                                ->label('Wymagana odpowiedz')
                                ->default(false),
                            Toggle::make('allow_not_applicable')
                                ->label('N/D dozwolone')
                                ->default(true),
                            Toggle::make('require_comment_when_na')
                                ->label('Komentarz przy N/D')
                                ->default(false),
                            Toggle::make('require_photo')
                                ->label('Zdjecie wymagane')
                                ->default(false),
                            Toggle::make('require_screenshot')
                                ->label('Screenshot wymagany')
                                ->default(false),
                            Toggle::make('risk_enabled')
                                ->label('Ocena ryzyka')
                                ->default(false),
                        ]),
                    ]),
                Section::make('Konfiguracja typu pola')
                    ->schema([
                        KeyValue::make('config_json')
                            ->label('Konfiguracja JSON')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartosc')
                            ->addActionLabel('Dodaj opcje')
                            ->columnSpanFull(),
                    ]),
                Section::make('Powiazane rekomendacje')
                    ->schema([
                        Select::make('recommendations')
                            ->label('Rekomendacje')
                            ->relationship('recommendations', 'title')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('module.name')
                    ->label('Modul')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('question')
                    ->label('Pytanie')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('field_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AuditQuestion::FIELD_TYPES[$state] ?? $state),
                IconColumn::make('is_required')
                    ->label('Wym.')
                    ->boolean(),
                IconColumn::make('require_photo')
                    ->label('Foto')
                    ->boolean(),
                IconColumn::make('require_screenshot')
                    ->label('Screen')
                    ->boolean(),
                IconColumn::make('risk_enabled')
                    ->label('Ryzyko')
                    ->boolean(),
                TextColumn::make('recommendations_count')
                    ->label('Rek.')
                    ->counts('recommendations')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktywne')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('audit_module_id')
                    ->label('Modul')
                    ->relationship('module', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('field_type')
                    ->label('Typ pola')
                    ->options(AuditQuestion::FIELD_TYPES),
                TernaryFilter::make('is_required')
                    ->label('Wymagane'),
                TernaryFilter::make('active')
                    ->label('Aktywne'),
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
            'index' => ManageAuditQuestions::route('/'),
        ];
    }
}
