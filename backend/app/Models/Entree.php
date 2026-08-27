<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Recherchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Écran 1 « Entrées » (§2) : remplissage de la cuve.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date_entree
 * @property string $fournisseur
 * @property float $quantite_litres
 * @property float $prix_unitaire
 * @property float $montant
 */
class Entree extends Model
{
    use Recherchable;

    protected $fillable = [
        'date_entree',
        'carburant_id',
        'fournisseur',
        'quantite_litres',
        'prix_unitaire',
        'reference_bon',
    ];

    protected $appends = ['montant'];

    protected function casts(): array
    {
        return [
            'date_entree' => 'datetime',
            'quantite_litres' => 'float',
            'prix_unitaire' => 'float',
        ];
    }

    public function carburant(): BelongsTo
    {
        return $this->belongsTo(Carburant::class);
    }

    /** Montant de la livraison, jamais stocké : toujours quantité × prix unitaire. */
    protected function montant(): Attribute
    {
        return Attribute::get(
            fn (): float => round($this->quantite_litres * $this->prix_unitaire, 2),
        );
    }

    /** @return array<int, string> */
    protected function colonnesRecherchees(): array
    {
        return ['fournisseur', 'reference_bon'];
    }

    public function scopeDuMois(Builder $query, int $annee, int $mois): Builder
    {
        return $query->whereYear('date_entree', $annee)->whereMonth('date_entree', $mois);
    }
}
