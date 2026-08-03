<?php

namespace App\Filament\Resources\AuditTypeVersions;

use App\Enums\CompetencyLevel;
use App\Filament\Resources\AuditTypeVersions\Pages\ManageAuditTypeVersions;
use App\Models\AuditTypeVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditTypeVersionResource extends Resource
{
    protected static ?string $model = AuditTypeVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $modelLabel = 'wersja typu audytu';

    protected static ?string $pluralModelLabel = 'wersje typow audytow';

    protected static ?string $navigationLabel = 'Wersje audytow';

    protected static string|UnitEnum|null $navigationGroup = 'Biblioteka audytow';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Wersja')->schema([
                Grid::make(2)->schema([
                    Select::make('audit_type_id')->label('Typ audytu')->relationship('auditType', 'name')->searchable()->preload()->required(),
                    TextInput::make('version')->label('Numer wersji')->numeric()->minValue(1)->required(),
                    TextInput::make('name_snapshot')->label('Nazwa wersji')->required()->maxLength(255),
                    Select::make('minimum_competency_level')->label('Minimalna kompetencja')->options(CompetencyLevel::options()),
                    Textarea::make('description_snapshot')->label('Opis')->rows(3)->columnSpanFull(),
                    Textarea::make('sales_instructions')->label('Instrukcje dla Sales')->rows(4)->columnSpanFull(),
                    Textarea::make('delivery_instructions')->label('Instrukcje dla inzyniera')->rows(5)->columnSpanFull(),
                ]),
            ]),
            Section::make('Szacowane czasy')->schema([
                Grid::make(4)->schema([
                    TextInput::make('estimated_preparation_minutes')->label('Przygotowanie')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('estimated_execution_minutes')->label('Realizacja')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('estimated_reporting_minutes')->label('Raport')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('estimated_review_minutes')->label('Weryfikacja')->numeric()->minValue(0)->default(0)->required(),
                ]),
            ]),
            Section::make('Domyslne parametry wyceny')->schema([
                Grid::make(4)->schema([
                    TextInput::make('default_hourly_rate')->label('Stawka godzinowa PLN')->numeric()->minValue(0),
                    TextInput::make('minimum_hours')->label('Minimum godzin')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('minimum_price')->label('Minimum ceny netto')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('reserve_percent')->label('Rezerwa %')->numeric()->minValue(0)->maxValue(100)->default(0)->required(),
                    TextInput::make('default_engineers_count')->label('Liczba inzynierow')->numeric()->minValue(1)->default(1)->required(),
                    TextInput::make('default_tax_rate')->label('VAT %')->numeric()->minValue(0)->default(23)->required(),
                    TextInput::make('default_validity_days')->label('Waznosc dni')->numeric()->minValue(1)->default(14)->required(),
                ]),
            ]),
            Section::make('Przyszla analiza AI')->schema([
                Toggle::make('ai_enabled')->label('AI wlaczone')->default(false),
                KeyValue::make('ai_configuration')->label('Konfiguracja AI')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('auditType.name')->label('Typ audytu')->searchable()->sortable(),
            TextColumn::make('version')->label('Wersja')->prefix('v')->sortable(),
            TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => AuditTypeVersion::STATUSES[$state] ?? $state),
            TextColumn::make('minimum_competency_level')->label('Kompetencja')->formatStateUsing(fn (?CompetencyLevel $state): string => $state?->label() ?? '-'),
            TextColumn::make('modules_count')->label('Moduly')->counts('modules'),
            IconColumn::make('ai_enabled')->label('AI')->boolean(),
            TextColumn::make('published_at')->label('Publikacja')->dateTime('Y-m-d H:i')->placeholder('-'),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(AuditTypeVersion::STATUSES),
            SelectFilter::make('audit_type_id')->label('Typ audytu')->relationship('auditType', 'name')->searchable()->preload(),
        ])->recordActions([
            Action::make('publish')
                ->label('Opublikuj wersje')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (AuditTypeVersion $record): bool => auth()->user()?->can('publish', $record) ?? false)
                ->action(function (AuditTypeVersion $record): void {
                    $record->publish(auth()->user());
                    Notification::make()->title('Wersja zostala opublikowana')->success()->send();
                }),
            Action::make('archive')
                ->label('Archiwizuj')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->requiresConfirmation()
                ->visible(fn (AuditTypeVersion $record): bool => auth()->user()?->can('archive', $record) ?? false)
                ->action(function (AuditTypeVersion $record): void {
                    $record->archive(auth()->user());
                    Notification::make()->title('Wersja zostala zarchiwizowana')->success()->send();
                }),
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditTypeVersions::route('/')];
    }
}
