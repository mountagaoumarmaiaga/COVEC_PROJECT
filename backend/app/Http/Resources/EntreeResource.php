<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Entree */
class EntreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Horodatage complet, en texte : le front le decoupe sans passer
            // par un objet Date, donc sans conversion de fuseau surprise.
            'date_entree' => $this->date_entree->format('Y-m-d H:i:s'),
            'carburant_id' => $this->carburant_id,
            'carburant' => CarburantResource::make($this->whenLoaded('carburant')),
            'fournisseur' => $this->fournisseur,
            'quantite_litres' => $this->quantite_litres,
            'prix_unitaire' => $this->prix_unitaire,
            'montant' => $this->montant,
            'reference_bon' => $this->reference_bon,
        ];
    }
}
