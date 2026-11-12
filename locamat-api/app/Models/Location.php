<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'materiel_id',
    'user_id',
    'date_debut',
    'date_fin',
    'date_fin_initiale',
    'nb_jours',
    'quantite',
    'prix_total',
    'statut',
    'message',
    'motif_refus',
    'terminee_at',
    'prolongation_date_fin',
    'prolongation_montant',
    'prolongation_statut',
    'penalite_jours',
    'penalite_montant',
])]
class Location extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_CONFIRMEE = 'confirmee';

    public const STATUT_REFUSEE = 'refusee';

    public const STATUT_ANNULEE = 'annulee';

    public const STATUT_TERMINEE = 'terminee';

    public static function montantMajoreEnCentimes(int $jours, int $prixJournalierEnCentimes): int
    {
        return intdiv($jours * $prixJournalierEnCentimes * 110 + 50, 100);
    }

    /**
     * Nombre de jours d'une période, date de début et date de fin incluses (du 6 au 10 : 5 jours).
     */
    public static function nombreJoursInclus(CarbonInterface|string $debut, CarbonInterface|string $fin): int
    {
        return Carbon::parse($debut)->startOfDay()->diffInDays(Carbon::parse($fin)->startOfDay()) + 1;
    }

    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
            'date_fin_initiale' => 'date:Y-m-d',
            'quantite' => 'integer',
            'prix_total' => 'decimal:2',
            'terminee_at' => 'datetime',
            'prolongation_date_fin' => 'date:Y-m-d',
            'prolongation_montant' => 'decimal:2',
            'penalite_jours' => 'integer',
            'penalite_montant' => 'decimal:2',
        ];
    }

    public function materiel(): BelongsTo
    {
        return $this->belongsTo(Materiel::class)->withTrashed();
    }

    public function scopeConsommeStockDepuis(Builder $query, string $date): Builder
    {
        return $query
            ->where('statut', self::STATUT_CONFIRMEE)
            ->whereDate('date_debut', '<=', $date);
    }

    public function scopeReserveStock(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_CONFIRMEE);
    }

    public function locataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(LocationHistorique::class)->orderBy('id');
    }

    public function estEnRetard(): bool
    {
        return $this->statut === self::STATUT_CONFIRMEE && $this->date_fin->isBefore(today());
    }
}
