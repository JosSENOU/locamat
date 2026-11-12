<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'location_id',
    'locataire_id',
    'proprietaire_id',
    'motif',
    'description',
    'statut',
    'note_admin',
    'traitee_at',
])]
class Reclamation extends Model
{
    public const STATUT_NOUVELLE = 'nouvelle';

    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_TRAITEE = 'traitee';

    public const STATUT_REJETEE = 'rejetee';

    public const STATUTS = [
        self::STATUT_NOUVELLE,
        self::STATUT_EN_COURS,
        self::STATUT_TRAITEE,
        self::STATUT_REJETEE,
    ];

    public const MOTIFS = [
        'comportement_inapproprie',
        'materiel_non_conforme',
        'paiement',
        'securite',
        'autre',
    ];

    protected function casts(): array
    {
        return [
            'traitee_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function locataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locataire_id')->withTrashed();
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id')->withTrashed();
    }
}
