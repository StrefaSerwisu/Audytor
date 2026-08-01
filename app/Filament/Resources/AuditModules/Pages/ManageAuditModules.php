<?php

namespace App\Filament\Resources\AuditModules\Pages;

use App\Filament\Resources\AuditModules\AuditModuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditModules extends ManageRecords
{
    protected static string $resource = AuditModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
