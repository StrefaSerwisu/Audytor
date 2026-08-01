<?php

namespace App\Filament\Resources\AuditTemplates\Pages;

use App\Filament\Resources\AuditTemplates\AuditTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditTemplates extends ManageRecords
{
    protected static string $resource = AuditTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
