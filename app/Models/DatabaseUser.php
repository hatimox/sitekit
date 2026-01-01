<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseUser extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'database_id',
        'server_id',
        'username',
        'password',
        'can_remote',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'can_remote' => 'boolean',
        ];
    }

    protected $hidden = [
        'password',
    ];

    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
