<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'name_en', 'description_en', 'rarity'];

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name;
    }

    public function getDisplayDescriptionAttribute(): string
    {
        return app()->getLocale() === 'en' ? $this->description_en : $this->description;
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
