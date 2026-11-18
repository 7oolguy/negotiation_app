<?php

namespace App\Filament\Resources\CommercialContracts\Pages;

use App\Filament\Resources\CommercialContracts\CommercialContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommercialContracts extends ListRecords
{
    protected static string $resource = CommercialContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
