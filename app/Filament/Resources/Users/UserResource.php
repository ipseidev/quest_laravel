<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Support lookup for a single account. Strictly read-only, and strictly
 * content-free.
 *
 * What this deliberately cannot show: page titles, page bodies, quest and person
 * names, quotes, chapters. Those columns are encrypted at rest and decrypt
 * transparently on read, so displaying them would take one careless column
 * definition — the guard is that no code path here ever loads a content model.
 * Everything below is a count, a date or a setting.
 *
 * The intended use is answering "my pages are gone" or "I paid and the app still
 * says free" without reading anybody's journal to do it.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    /**
     * Counts come from query-builder subselects rather than `withCount()`, which
     * would route through the content models and their `BelongsToCurrentUserScope`.
     * That scope filters on `Auth::id()` for the *default* guard, so the counts
     * happen to be right today only because the panel authenticates on `admin`
     * instead. Subselects do not depend on that holding.
     */
    public static function getEloquentQuery(): Builder
    {
        $countOf = fn (string $table) => DB::table($table)
            ->selectRaw('count(*)')
            ->whereColumn($table.'.user_id', 'users.id');

        return parent::getEloquentQuery()
            ->addSelect([
                'entries_count' => (clone $countOf('entries'))->where('is_deleted', false),
                'quests_count' => $countOf('quests'),
                'characters_count' => $countOf('characters'),
                'last_seen_at' => DB::table('personal_access_tokens')
                    ->selectRaw('max(last_used_at)')
                    ->whereColumn('tokenable_id', 'users.id')
                    ->where('tokenable_type', User::class),
                'platform' => DB::table('devices')
                    ->select('platform')
                    ->whereColumn('devices.user_id', 'users.id')
                    ->whereNotNull('platform')
                    ->orderByRaw('last_seen_at desc nulls last')
                    ->limit(1),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    /*
     * An account is created by someone installing the app, and edited by them
     * using it. Neither should ever happen from here — an operator editing a
     * journal account is a support incident, not a feature.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
