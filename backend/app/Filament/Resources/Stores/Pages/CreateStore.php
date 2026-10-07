<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    /**
     * Use forceFill to allow tenant_id assignment from the form.
     * The BelongsToTenant trait's creating hook will not overwrite
     * tenant_id if it's already set on the model.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $record = static::getModel()::forceFill($data);
        $record->save();

        return $record;
    }
}
