<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ThroughEntryToCurrentUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $table = $model->getTable();
        $userId = Auth::id();

        $builder->whereExists(function ($q) use ($table, $userId) {
            $q->select(DB::raw(1))
                ->from('entries')
                ->whereColumn('entries.id', $table.'.entry_id')
                ->where('entries.user_id', $userId);
        });
    }
}
