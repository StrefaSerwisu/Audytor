<?php

namespace App\Filament\Resources\SalesQualificationQuestions\Pages;

use App\Filament\Resources\SalesQualificationQuestions\SalesQualificationQuestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalesQualificationQuestions extends ManageRecords
{
    protected static string $resource = SalesQualificationQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
