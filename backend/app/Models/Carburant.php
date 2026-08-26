<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Carburant distribué par la station : gasoil ou essence.
 *
 * Chaque carburant a sa cuve, son stock et son prix moyen. Le prix par défaut
 * ne sert qu'à pré-remplir la saisie d'une livraison ; c'est le prix porté sur
 * le bon de livraison qui est enregistré et qui entre dans les calculs.
 *
 * @property int $id
 * @property string $code
 * @property string $libelle
 * @property float $prix_par_defaut
 * @property bool $actif
 */
class Carburant extends Model
{
    protected $fillable = ['code', 'libelle', 'prix_par_defaut', 'actif'];

    protected function casts(): array
    {
        return [
            'prix_par_defaut' => 'float',
            'actif' => 'boolean',
        ];
    }

    public function cuve(): HasOne
    {
        return $this->hasOne(Cuve::class);
    }

    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    public function entrees(): HasMany
    {
        return $this->hasMany(Entree::class);
    }

    /**
     * Les sorties de ce carburant, atteintes par les véhicules qui le consomment.
     *
     * Le carburant d'une sortie n'est pas stocké : il se déduit du véhicule.
     * Corriger le carburant d'un véhicule mal renseigné doit corriger son
     * historique, puisque les litres servis étaient bien de ce carburant-là.
     */
    public function sorties(): HasManyThrough
    {
        return $this->hasManyThrough(Sortie::class, Vehicule::class);
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
