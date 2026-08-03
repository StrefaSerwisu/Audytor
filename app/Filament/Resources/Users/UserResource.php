<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'uzytkownik';

    protected static ?string $pluralModelLabel = 'uzytkownicy';

    protected static ?string $navigationLabel = 'Uzytkownicy';

    protected static string|UnitEnum|null $navigationGroup = 'Bezpieczenstwo';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Konto')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Imie i nazwisko')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label('Haslo')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(12)
                            ->maxLength(255),
                        Select::make('role')
                            ->label('Rola')
                            ->options(fn (): array => self::roleOptions())
                            ->required()
                            ->disabled(fn (?User $record): bool => auth()->id() === $record?->id)
                            ->live(),
                        Select::make('client_id')
                            ->label('Klient')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('role') === UserRole::Client->value)
                            ->visible(fn (Get $get): bool => $get('role') === UserRole::Client->value),
                        Toggle::make('active')
                            ->label('Konto aktywne')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => auth()->id() === $record?->id),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Uzytkownik')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->sortable(),
                TextColumn::make('role')
                    ->label('Rola')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label()),
                TextColumn::make('client.name')->label('Klient')->placeholder('Global IT')->sortable(),
                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktywny' : 'Nieaktywny')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('updated_at')->label('Aktualizacja')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rola')->options(self::allRoleOptions()),
                SelectFilter::make('active')->label('Status')->options([
                    '1' => 'Aktywny',
                    '0' => 'Nieaktywny',
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => self::prepareFormData($data)),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ]);
    }

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        $options = self::allRoleOptions();

        if (! auth()->user()?->hasRole(UserRole::SuperAdmin)) {
            unset($options[UserRole::SuperAdmin->value]);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareFormData(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if (($data['role'] ?? null) !== UserRole::Client->value) {
            $data['client_id'] = null;
        }

        if (($data['role'] ?? null) === UserRole::Client->value && blank($data['client_id'] ?? null)) {
            throw ValidationException::withMessages([
                'client_id' => 'Konto klienta musi byc przypisane do klienta.',
            ]);
        }

        return $data;
    }

    /** @return array<string, string> */
    private static function allRoleOptions(): array
    {
        return collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->label()])
            ->all();
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->getAttribute('name') ?? 'Uzytkownik';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
