<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;

class MemoraUserFileStorage extends Model
{
    protected $table = 'memora_user_file_storage';

    protected $primaryKey = 'user_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'total_storage_bytes',
        'last_calculated_at',
    ];

    protected $casts = [
        'total_storage_bytes' => 'integer',
        'last_calculated_at' => 'datetime',
    ];
}
