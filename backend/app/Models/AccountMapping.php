<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    protected $fillable = ['key', 'account_id', 'description'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function getAccount(string $key): Account
    {
        $mapping = static::query()->where('key', $key)->with('account')->first();

        if (! $mapping) {
            throw new \RuntimeException("Account mapping '{$key}' tidak ditemukan. Jalankan AccountMappingSeeder.");
        }

        return $mapping->account;
    }
}
