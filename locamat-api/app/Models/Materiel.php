<?php

namespace App\Models;

use Database\Factories\MaterielFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nom',
    'categorie',
    'description',
    'photo',
    'prix_journalier',
    'etat',
    'disponible',
])]
class Materiel extends Model
{
    /** @use HasFactory<MaterielFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'prix_journalier' => 'decimal:2',
            'disponible' => 'boolean',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
