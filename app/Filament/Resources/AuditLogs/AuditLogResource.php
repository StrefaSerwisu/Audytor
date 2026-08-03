<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'wpis dziennika';

    protected static ?string $pluralModelLabel = 'dziennik zdarzen';

    protected static ?string $navigationLabel = 'Dziennik zdarzen';

    protected static string|UnitEnum|null $navigationGroup = 'Bezpieczenstwo';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('actor.name')->label('Uzytkownik')->placeholder('System')->searchable(),
                TextColumn::make('event')->label('Zdarzenie')->badge()->searchable(),
                TextColumn::make('subject_type')
                    ->label('Obiekt')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'System'),
                TextColumn::make('subject_id')->label('ID')->placeholder('-'),
                TextColumn::make('old_values')
                    ->label('Poprzednie wartosci')
                    ->state(fn (AuditLog $record): string => self::formatValues($record->old_values))
                    ->placeholder('-')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('new_values')
                    ->label('Nowe wartosci')
                    ->state(fn (AuditLog $record): string => self::formatValues($record->new_values))
                    ->placeholder('-')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('ip_address')->label('IP')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('event')->label('Zdarzenie')->options([
                    'user.created' => 'Utworzenie uzytkownika',
                    'user.updated' => 'Zmiana uzytkownika',
                    'user.deleted' => 'Usuniecie uzytkownika',
                    'evidence.downloaded' => 'Pobranie dowodu',
                    'evidence.deleted' => 'Usuniecie dowodu',
                    'audit.submitted_for_review' => 'Wyslanie audytu',
                    'audit.technically_approved' => 'Zatwierdzenie audytu',
                    'audit.changes_requested' => 'Zwrot do poprawek',
                    'audit.closed' => 'Zamkniecie audytu',
                    'report.published' => 'Publikacja raportu',
                    'report.downloaded' => 'Pobranie raportu',
                    'report_export.queued' => 'Zlecenie eksportu',
                    'report_export.downloaded' => 'Pobranie eksportu',
                    'report_export.retried' => 'Ponowienie eksportu',
                    'follow_up.updated' => 'Zmiana follow-up',
                    'client_report.status_updated' => 'Decyzja klienta',
                    'client_report.feedback_updated' => 'Komentarz klienta',
                    'auth.login_succeeded' => 'Udane logowanie',
                    'auth.login_failed' => 'Nieudane logowanie',
                    'auth.login_denied' => 'Odrzucone logowanie',
                    'auth.logout' => 'Wylogowanie',
                ]),
            ]);
    }

    /** @param array<string, mixed>|null $values */
    private static function formatValues(?array $values): string
    {
        return collect($values ?? [])
            ->map(fn (mixed $value, string $key): string => $key.': '.match (true) {
                is_bool($value) => $value ? 'tak' : 'nie',
                is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
                $value === null => 'brak',
                default => (string) $value,
            })
            ->join(', ');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
