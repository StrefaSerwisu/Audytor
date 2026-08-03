<?php

namespace App\Filament\Resources\AuditControlDefinitions\Pages;

use App\Filament\Resources\AuditControlDefinitions\AuditControlDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditControlDefinitions extends ManageRecords
{
    protected static string $resource = AuditControlDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
