<?php

namespace App\Filament\Resources\Recommendations;

use App\Filament\Resources\Recommendations\Pages\ManageRecommendations;
use App\Models\Recommendation;
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

class RecommendationResource extends Resource
{
    protected static ?string $model = Recommendation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $modelLabel = 'rekomendacja';

    protected static ?string $pluralModelLabel = 'rekomendacje';

    protected static ?string $navigationLabel = 'Rekomendacje';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytu';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rekomendacja')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->label('Tytul')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('risk_level')
                                ->label('Poziom ryzyka')
                                ->options(Recommendation::RISK_LEVELS)
                                ->searchable(),
                            Select::make('priority')
                                ->label('Priorytet')
                                ->options(Recommendation::PRIORITIES)
                                ->searchable(),
                            Textarea::make('technical_description')
                                ->label('Opis techniczny')
                                ->rows(5)
                                ->columnSpanFull(),
                            Textarea::make('business_description')
                                ->label('Opis biznesowy')
                                ->rows(5)
                                ->columnSpanFull(),
                            Textarea::make('recommendation_text')
                                ->label('Rekomendacja')
                                ->required()
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Realizacja i sprzedaz')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('suggested_deadline')
                                ->label('Sugerowany termin')
                                ->maxLength(255),
                            TextInput::make('estimated_hours_min')
                                ->label('Min. godzin')
                                ->numeric(),
                            TextInput::make('estimated_hours_max')
                                ->label('Max. godzin')
                                ->numeric(),
                            Toggle::make('global_it_can_do')
                                ->label('Global IT moze wykonac')
                                ->default(true),
                            TextInput::make('sales_category')
                                ->label('Kategoria sprzedazowa')
                                ->maxLength(255),
                            Toggle::make('active')
                                ->label('Aktywna')
                                ->default(true),
                            KeyValue::make('tags_json')
                                ->label('Tagi')
                                ->keyLabel('Tag')
                                ->valueLabel('Wartosc')
                                ->addActionLabel('Dodaj tag')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Powiazane pytania')
                    ->schema([
                        Select::make('questions')
                            ->label('Pytania audytowe')
                            ->relationship('questions', 'question')
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
                TextColumn::make('title')
                    ->label('Tytul')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('risk_level')
                    ->label('Ryzyko')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (Recommendation::RISK_LEVELS[$state] ?? $state) : '-'),
                TextColumn::make('priority')
                    ->label('Priorytet')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (Recommendation::PRIORITIES[$state] ?? $state) : '-'),
                TextColumn::make('sales_category')
                    ->label('Kategoria')
                    ->searchable(),
                TextColumn::make('estimated_hours_min')
                    ->label('Min h')
                    ->sortable(),
                TextColumn::make('estimated_hours_max')
                    ->label('Max h')
                    ->sortable(),
                IconColumn::make('global_it_can_do')
                    ->label('GIT')
                    ->boolean(),
                TextColumn::make('questions_count')
                    ->label('Pytania')
                    ->counts('questions')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktywna')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('risk_level')
                    ->label('Ryzyko')
                    ->options(Recommendation::RISK_LEVELS),
                SelectFilter::make('priority')
                    ->label('Priorytet')
                    ->options(Recommendation::PRIORITIES),
                SelectFilter::make('sales_category')
                    ->label('Kategoria sprzedazowa')
                    ->options(fn (): array => Recommendation::query()
                        ->whereNotNull('sales_category')
                        ->distinct()
                        ->orderBy('sales_category')
                        ->pluck('sales_category', 'sales_category')
                        ->all()),
                TernaryFilter::make('global_it_can_do')
                    ->label('Global IT moze wykonac'),
                TernaryFilter::make('active')
                    ->label('Aktywna'),
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
            'index' => ManageRecommendations::route('/'),
        ];
    }
}
