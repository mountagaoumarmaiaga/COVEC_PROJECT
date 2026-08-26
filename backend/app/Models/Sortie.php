<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Écran 2 « Sorties » (§2) : un véhicule se sert à la cuve.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date_sortie
 * @property float $litres_servis
 * @property float $prix_unitaire
 * @property float $montant
 * @property float $index_compteur
 * @property float|null $index_pompe
 * @property float|null $distance_parcourue
 * @property float|null $consommation
 * @property float|null $moyenne_reference
 * @property float|null $ecart_pourcentage
 * @property bool $anomalie
 */
class Sortie extends Model
{
    /**
     * Les colonnes calculées sont volontairement absentes : seul
     * ConsommationService les écrit, jamais une requête HTTP.
     */
    protected $appends = ['montant'];

    protected $fillable = [
        'date_sortie',
        'vehicule_id',
        'chauffeur_id',
        'litres_servis',
        'prix_unitaire',
        'index_compteur',
        'index_pompe',
    ];

    protected function casts(): array
    {
        return [
            'date_sortie' => 'datetime',
            'litres_servis' => 'float',
            'prix_unitaire' => 'float',
            'index_compteur' => 'float',
            'index_pompe' => 'float',
            'distance_parcourue' => 'float',
            'consommation' => 'float',
            'moyenne_reference' => 'float',
            'ecart_pourcentage' => 'float',
            'anomalie' => 'boolean',
        ];
    }

    /** Montant du plein, jamais stocké : toujours litres × prix enregistré. */
    protected function montant(): Attribute
    {
        return Attribute::get(
            fn (): float => round($this->litres_servis * $this->prix_unitaire, 2),
        );
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function scopeDuMois(Builder $query, int $annee, int $mois): Builder
    {
        return $query->whereYear('date_sortie', $annee)->whereMonth('date_sortie', $mois);
    }

    public function scopeChronologique(Builder $query): Builder
    {
        return $query->orderBy('date_sortie')->orderBy('id');
    }
}
