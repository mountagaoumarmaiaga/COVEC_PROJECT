<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Carburant */
class CarburantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            // Pré-remplit le prix du litre à la saisie d'une livraison ; le
            // prix réellement facturé reste saisi ligne par ligne.
            'prix_par_defaut' => $this->prix_par_defaut,
            'actif' => $this->actif,
            'cuve' => $this->whenLoaded('cuve', fn () => [
                'id' => $this->cuve?->id,
                'nom' => $this->cuve?->nom,
                'capacite' => $this->cuve?->capacite,
            ]),
        ];
    }
}
