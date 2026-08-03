<?php

namespace App\Filament\Resources\AuditTypeVersions\Pages;

use App\Filament\Resources\AuditTypeVersions\AuditTypeVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditTypeVersions extends ManageRecords
{
    protected static string $resource = AuditTypeVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
