<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code',
        'name',
        'description',
        'location',
        'acquisition_date',
        'cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'accumulated_depreciation',
        'book_value',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'book_value' => 'decimal:2',
        ];
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
