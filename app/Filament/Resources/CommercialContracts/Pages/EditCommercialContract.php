<?php

namespace App\Filament\Resources\CommercialContracts\Pages;

use App\Filament\Resources\CommercialContracts\CommercialContractResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCommercialContract extends EditRecord
{
    protected static string $resource = CommercialContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
