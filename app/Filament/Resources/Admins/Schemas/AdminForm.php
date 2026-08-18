<?php

namespace App\Filament\Resources\Admins\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                // Scoped to ignore the record being edited, so saving a form
                // without touching the address does not collide with itself.
                ->unique(ignoreRecord: true),

            TextInput::make('password')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                // Same floor as `nacre:admin`. A panel that quietly accepted weaker
                // passwords than the command would make the command's rule decorative.
                ->rule(Password::min(12))
                ->required(fn (string $operation): bool => $operation === 'create')
                /*
                 * Left out of the payload entirely when blank, which is what makes
                 * "edit a name without resetting the password" work: the field is
                 * always empty on load, so dehydrating it unconditionally would
                 * overwrite the stored hash with an empty string on every save.
                 */
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation): string => $operation === 'edit'
                    ? 'Leave blank to keep the current password.'
                    : 'At least 12 characters.'),
        ]);
    }
}
