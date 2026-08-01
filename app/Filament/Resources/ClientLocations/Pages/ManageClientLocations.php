<?php

namespace App\Filament\Resources\ClientLocations\Pages;

use App\Filament\Resources\ClientLocations\ClientLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageClientLocations extends ManageRecords
{
    protected static string $resource = ClientLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
