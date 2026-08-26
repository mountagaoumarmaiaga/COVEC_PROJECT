<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Vehicule */
class VehiculeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'designation' => $this->designation,
            'mode_suivi' => $this->mode_suivi->value,
            'mode_suivi_libelle' => $this->mode_suivi->libelle(),
            // Les unités voyagent avec le véhicule pour que l'interface n'ait
            // pas à réimplémenter la correspondance km → L/100 km.
            'unite_index' => $this->mode_suivi->uniteIndex(),
            'unite_consommation' => $this->mode_suivi->uniteConsommation(),
            'capacite_reservoir' => $this->capacite_reservoir,
            'carburant_id' => $this->carburant_id,
            'carburant' => CarburantResource::make($this->whenLoaded('carburant')),
            'actif' => $this->actif,
        ];
    }
}
