<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['materiel_id', 'path', 'position'])]
class MaterielPhoto extends Model
{
    public function materiel(): BelongsTo
    {
        return $this->belongsTo(Materiel::class);
    }
}
