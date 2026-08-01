<?php

namespace App\Filament\Resources\AuditQuestions\Pages;

use App\Filament\Resources\AuditQuestions\AuditQuestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditQuestions extends ManageRecords
{
    protected static string $resource = AuditQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
