<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginSession extends Model
{
    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'ip_address',
        'user_agent',
        'remember',
        'login_at',
        'logout_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'remember' => 'boolean',
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
