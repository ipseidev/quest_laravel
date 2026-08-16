<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('No email on file')
                    ->description(fn ($record): string => (string) $record->id)
                    ->wrap(),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->placeholder('unknown')
                    ->color(fn (?string $state): string => match ($state) {
                        'ios' => 'info',
                        'android' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('plan')
                    ->label('Plan')
                    ->badge()
                    // Deferred to the model so a lifetime entitlement — product id
                    // set, expiry null — reads as Plus here too.
                    ->state(fn (User $record): string => $record->hasActiveSubscription() ? 'Plus' : 'Free')
                    ->color(fn (string $state): string => $state === 'Plus' ? 'warning' : 'gray'),

                TextColumn::make('entries_count')
                    ->label('Pages')
                    ->numeric()
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('created_at')
                    ->label('Signed up')
                    ->date('j M Y')
                    ->sortable(),

                TextColumn::make('last_seen_at')
                    ->label('Last sync')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),

                IconColumn::make('ai_chapters_opt_in')
                    ->label('AI')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge()
                    ->placeholder('unset')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('locale')
                    ->options(['fr' => 'Français', 'en' => 'English'])
                    ->label('Language'),

                Filter::make('plus')
                    ->label('Plus subscribers')
                    ->query(fn (Builder $query): Builder => $query->withActiveSubscription()),

                Filter::make('dormant')
                    ->label('No sync in 30 days')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereRaw(
                            '(select max(last_used_at) from personal_access_tokens
                              where tokenable_id = users.id and tokenable_type = ?) < ?
                             or not exists (select 1 from personal_access_tokens
                              where tokenable_id = users.id and tokenable_type = ?)',
                            [User::class, Carbon::now()->subDays(30), User::class],
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            // No bulk actions: every destructive thing you could do to a journal
            // account from here is something the account owner should do in the app.
            ->toolbarActions([]);
    }
}
