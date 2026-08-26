<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModeSuivi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel « Véhicules et engins » (§3).
 *
 * @property int $id
 * @property string $code
 * @property string $designation
 * @property ModeSuivi $mode_suivi
 * @property int $carburant_id
 * @property float $capacite_reservoir
 * @property bool $actif
 */
class Vehicule extends Model
{
    protected $fillable = [
        'code',
        'designation',
        'carburant_id',
        'mode_suivi',
        'capacite_reservoir',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'mode_suivi' => ModeSuivi::class,
            'capacite_reservoir' => 'float',
            'actif' => 'boolean',
        ];
    }

    public function carburant(): BelongsTo
    {
        return $this->belongsTo(Carburant::class);
    }

    public function sorties(): HasMany
    {
        return $this->hasMany(Sortie::class);
    }

    /**
     * Les sorties dans l'ordre où la consommation se calcule.
     *
     * L'identifiant départage deux pleins saisis à la même date : sans lui
     * l'ordre serait indéterminé et la chaîne de consommation instable.
     */
    public function sortiesChronologiques(): HasMany
    {
        return $this->sorties()->orderBy('date_sortie')->orderBy('id');
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
