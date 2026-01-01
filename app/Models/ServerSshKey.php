<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ServerSshKey extends Pivot
{
    use HasUuids;

    protected $table = 'server_ssh_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'server_id',
        'ssh_key_id',
        'status',
    ];
}
