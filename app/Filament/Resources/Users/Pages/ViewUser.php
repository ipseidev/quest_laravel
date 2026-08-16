<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * No header actions. There is no edit, no delete and no impersonate: every one
     * of those would be an operator reaching into somebody's journal.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
