<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Sortie */
class SortieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date_sortie' => $this->date_sortie->format('Y-m-d H:i:s'),
            'litres_servis' => $this->litres_servis,
            'prix_unitaire' => $this->prix_unitaire,
            'montant' => $this->montant,
            'index_compteur' => $this->index_compteur,
            'index_pompe' => $this->index_pompe,

            // Résultats du calcul de consommation. Tous nuls tant que le
            // véhicule n'a pas au moins deux pleins : le premier ne sert que
            // de point de départ au compteur.
            'distance_parcourue' => $this->distance_parcourue,
            'consommation' => $this->consommation,
            'moyenne_reference' => $this->moyenne_reference,
            'ecart_pourcentage' => $this->ecart_pourcentage,
            'anomalie' => $this->anomalie,

            'vehicule' => VehiculeResource::make($this->whenLoaded('vehicule')),
            'chauffeur' => ChauffeurResource::make($this->whenLoaded('chauffeur')),
        ];
    }
}
