<?php

namespace App\Models;

use Database\Factories\MaterielFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nom',
    'user_id',
    'categorie_id',
    'description',
    'prix_journalier',
    'etat',
    'etats_exemplaires',
    'disponible',
    'quantite',
])]
class Materiel extends Model
{
    /** @use HasFactory<MaterielFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'prix_journalier' => 'decimal:2',
            'disponible' => 'boolean',
            'quantite' => 'integer',
            'etats_exemplaires' => 'array',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categorie_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaterielPhoto::class)->orderBy('position');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
