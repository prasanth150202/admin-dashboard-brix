<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['name', 'token_hash', 'prefix', 'created_by', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: self, 1: string} the stored key and the plain token (show it once) */
    public static function generate(string $name, ?User $by = null): array
    {
        $token = 'brix_'.Str::random(40);

        $key = self::create([
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'prefix' => substr($token, 0, 9),
            'created_by' => $by?->id,
        ]);

        return [$key, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return $token === '' ? null : self::where('token_hash', hash('sha256', $token))->first();
    }
}
