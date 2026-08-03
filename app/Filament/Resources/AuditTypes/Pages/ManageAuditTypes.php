<?php

namespace App\Filament\Resources\AuditTypes\Pages;

use App\Filament\Resources\AuditTypes\AuditTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditTypes extends ManageRecords
{
    protected static string $resource = AuditTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
