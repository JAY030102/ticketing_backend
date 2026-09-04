<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedUser extends Model
{
    protected $fillable = [
        'original_user_id',
        'user_data',
        'termination_reason',
        'terminated_by',
        'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'user_data' => 'array',
            'terminated_at' => 'datetime',
        ];
    }

    public function terminatedBy()
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }
}
