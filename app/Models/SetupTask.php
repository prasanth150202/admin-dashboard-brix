<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetupTask extends Model
{
    protected $fillable = ['setup_id', 'title', 'position', 'done_at', 'done_by'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    public function setup(): BelongsTo
    {
        return $this->belongsTo(Setup::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
