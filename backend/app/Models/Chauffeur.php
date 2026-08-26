<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel « Chauffeurs » (§3) : nom, matricule.
 *
 * @property int $id
 * @property string $nom
 * @property string $matricule
 * @property bool $actif
 */
class Chauffeur extends Model
{
    protected $fillable = ['nom', 'matricule', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function sorties(): HasMany
    {
        return $this->hasMany(Sortie::class);
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
