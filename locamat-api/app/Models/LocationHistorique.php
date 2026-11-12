<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['location_id', 'user_id', 'evenement', 'statut', 'note'])]
class LocationHistorique extends Model
{
    public const UPDATED_AT = null;

    public const CREATION = 'creation';

    public const CONFIRMATION = 'confirmation';

    public const REFUS = 'refus';

    public const ANNULATION = 'annulation';

    public const PROLONGATION_DEMANDEE = 'prolongation_demandee';

    public const PROLONGATION_ACCEPTEE = 'prolongation_acceptee';

    public const PROLONGATION_REFUSEE = 'prolongation_refusee';

    public const RETOUR_SIGNALE = 'retour_signale';

    public const TERMINEE = 'terminee';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
