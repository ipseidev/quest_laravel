<?php

namespace App\Models;

use App\Services\Devices\DeviceRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One app installation behind an account. Written by
 * {@see DeviceRecorder}, read only by the admin panel.
 *
 * No global scope: unlike the content models this carries no journal data, and
 * the only reader is an operator query that would have to drop the scope anyway.
 */
class Device extends Model
{
    public const PLATFORMS = ['ios', 'android'];

    protected $fillable = [
        'user_id',
        'device_id',
        'platform',
        'app_version',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * Millisecond precision to match every other timestamp in this schema.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
