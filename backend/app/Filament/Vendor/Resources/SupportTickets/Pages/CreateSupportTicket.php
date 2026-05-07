<?php

namespace App\Filament\Vendor\Resources\SupportTickets\Pages;

use App\Actions\Support\CreateSupportTicket as CreateSupportTicketAction;
use App\Filament\Vendor\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateSupportTicketAction::class)->handle($data, auth()->user());
    }
}
