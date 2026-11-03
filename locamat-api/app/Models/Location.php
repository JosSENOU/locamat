<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'materiel_id',
    'locataire_id',
    'date_debut',
    'date_fin',
    'nb_jours',
    'prix_total',
    'statut',
    'message',
    'motif_refus',
    'terminee_at',
])]
class Location extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_CONFIRMEE = 'confirmee';

    public const STATUT_REFUSEE = 'refusee';

    public const STATUT_ANNULEE = 'annulee';

    public const STATUT_TERMINEE = 'terminee';

    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'prix_total' => 'decimal:2',
            'terminee_at' => 'datetime',
        ];
    }

    public function materiel(): BelongsTo
    {
        return $this->belongsTo(Materiel::class);
    }

    public function locataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locataire_id');
    }
}
