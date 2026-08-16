<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\Admin\Metrics;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * The single-account support view.
 *
 * Every entry here is a count, a timestamp or a setting. Nothing on this screen
 * reads an encrypted column, and nothing should be added that does.
 */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->columns(3)
                ->schema([
                    TextEntry::make('email')->placeholder('No email on file'),
                    TextEntry::make('id')->label('Account ID')->copyable(),
                    TextEntry::make('created_at')->label('Signed up')->dateTime('j M Y, H:i'),
                    TextEntry::make('locale')->label('Language')->placeholder('unset'),
                    TextEntry::make('sign_in')
                        ->label('Signs in with')
                        ->state(fn (User $record): string => collect([
                            $record->apple_id !== null ? 'Apple' : null,
                            $record->google_id !== null ? 'Google' : null,
                            $record->password !== null ? 'Password' : null,
                        ])->filter()->implode(', ') ?: 'Unknown'),
                    TextEntry::make('email_verified_at')
                        ->label('Email verified')
                        ->dateTime('j M Y')
                        ->placeholder('Not verified'),
                ]),

            Section::make('Subscription')
                ->columns(3)
                ->schema([
                    TextEntry::make('plan')
                        ->badge()
                        ->state(fn (User $record): string => $record->hasActiveSubscription() ? 'Plus' : 'Free')
                        ->color(fn (string $state): string => $state === 'Plus' ? 'warning' : 'gray'),
                    TextEntry::make('subscription_product_id')
                        ->label('Product')
                        ->placeholder('None')
                        ->copyable(),
                    TextEntry::make('subscription_expires_at')
                        ->label('Entitlement until')
                        ->dateTime('j M Y, H:i')
                        ->placeholder('—')
                        // The distinction that generates most support mail: a
                        // cancelled subscription still has access until this date.
                        ->helperText('A cancelled subscription keeps access until this date.'),
                    TextEntry::make('ai_chapters_opt_in')
                        ->label('AI chapters')
                        ->badge()
                        ->state(fn (User $record): string => $record->ai_chapters_opt_in ? 'Opted in' : 'Off')
                        ->color(fn (string $state): string => $state === 'Opted in' ? 'info' : 'gray'),
                ]),

            Section::make('Content')
                ->columns(4)
                ->description('Counts only. Titles, bodies, names and quotes are encrypted at rest and are not readable from this panel.')
                ->schema([
                    TextEntry::make('pages')
                        ->state(fn (User $record): int => self::count('entries', $record, true)),
                    TextEntry::make('quests')
                        ->state(fn (User $record): int => self::count('quests', $record)),
                    TextEntry::make('people')
                        ->state(fn (User $record): int => self::count('characters', $record)),
                    TextEntry::make('deleted_pages')
                        ->label('In trash')
                        ->state(fn (User $record): int => DB::table('entries')
                            ->where('user_id', $record->id)
                            ->where('is_deleted', true)
                            ->count())
                        ->helperText('Purged 30 days after deletion.'),
                    TextEntry::make('storage')
                        ->label('Media stored')
                        ->state(fn (User $record): string => self::storage($record)),
                ]),

            Section::make('Devices')
                ->description('Reported by the app from the first build that sends it. An older install shows no platform.')
                ->schema([
                    RepeatableEntry::make('devices')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('platform')->badge()->placeholder('unknown'),
                            TextEntry::make('app_version')->label('Version')->placeholder('unknown'),
                            TextEntry::make('first_seen_at')->label('First seen')->dateTime('j M Y'),
                            TextEntry::make('last_seen_at')->label('Last seen')->since(),
                        ]),
                ]),
        ]);
    }

    /**
     * Counted through the query builder so the content models' global scope cannot
     * apply. See {@see Metrics} for why that matters.
     */
    private static function count(string $table, User $user, bool $excludeDeleted = false): int
    {
        $query = DB::table($table)->where('user_id', $user->id);

        if ($excludeDeleted) {
            $query->where('is_deleted', false);
        }

        return $query->count();
    }

    private static function storage(User $user): string
    {
        $bytes = 0;

        foreach (['entry_attachments', 'entry_audio', 'entry_videos'] as $table) {
            $bytes += (int) DB::table($table)
                ->join('entries', 'entries.id', '=', $table.'.entry_id')
                ->where('entries.user_id', $user->id)
                ->sum($table.'.size_bytes');
        }

        return $bytes < 1024 ** 2
            ? round($bytes / 1024).' KB'
            : round($bytes / 1024 ** 2, 1).' MB';
    }
}
