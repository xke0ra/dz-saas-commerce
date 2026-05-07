<?php

namespace App\Filament\Vendor\Resources\Invoices\Pages;

use App\Filament\Vendor\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;
}
