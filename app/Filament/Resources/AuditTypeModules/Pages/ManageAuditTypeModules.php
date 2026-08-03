<?php

namespace App\Filament\Resources\AuditTypeModules\Pages;

use App\Filament\Resources\AuditTypeModules\AuditTypeModuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditTypeModules extends ManageRecords
{
    protected static string $resource = AuditTypeModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
