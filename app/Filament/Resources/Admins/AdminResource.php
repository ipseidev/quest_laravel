<?php

namespace App\Filament\Resources\Admins;

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\Admins\Schemas\AdminForm;
use App\Filament\Resources\Admins\Tables\AdminsTable;
use App\Models\Admin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Panel operators, managed from the panel.
 *
 * There are no roles on `admins`: every operator is equal, so anyone who can
 * reach this screen can create another operator with the same reach. That is the
 * right trade for a product with one person behind it, but it is the reason this
 * resource is worth reading before it grows — the day a second kind of operator
 * is needed, a role column has to land here first.
 *
 * `php artisan nacre:admin` stays: it is the only way to create the *first*
 * operator, when nobody can sign in yet.
 */
class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Operators';

    protected static ?string $modelLabel = 'operator';

    public static function form(Schema $schema): Schema
    {
        return AdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }

    /*
     * Deletion is refused for your own account and for the last remaining one. That
     * rule lives in `App\Policies\AdminPolicy`, not in a `canDelete()` override
     * here: Filament routes authorization through the Gate, so an override would be
     * honoured by some call sites and ignored by the delete button itself.
     */
}
