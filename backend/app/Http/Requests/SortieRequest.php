<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Sortie;
use App\Models\Vehicule;
use App\Rules\IndexCompteurCoherent;
use App\Rules\LitresDansCapaciteReservoir;
use App\Services\ConsommationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Saisie d'une sortie de carburant, en création comme en modification.
 *
 * Porte les deux contrôles bloquants du §5 du cahier des charges. Le
 * troisième — le plein signalé en rouge — n'est pas une validation : il
 * n'empêche pas la saisie, il la marque, et se calcule après enregistrement.
 */
class SortieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pose l'horodatage avant validation.
     *
     * En création, l'instant est celui du serveur et non celui du poste de
     * saisie : deux postes peuvent avoir des horloges déréglées différemment,
     * et l'ordre de la chaîne de consommation en dépend. En modification,
     * l'horodatage reste corrigeable ; s'il n'est pas renvoyé, celui déjà
     * enregistré est conservé.
     */
    protected function prepareForValidation(): void
    {
        $sortie = $this->route('sortie');

        if ($sortie instanceof Sortie) {
            if (! $this->filled('date_sortie')) {
                $this->merge(['date_sortie' => $sortie->date_sortie->format('Y-m-d H:i:s')]);
            }

            $this->merge(['prix_unitaire' => $this->prixConserve($sortie)]);

            return;
        }

        $this->merge([
            'date_sortie' => now()->format('Y-m-d H:i:s'),
            'prix_unitaire' => $this->prixEnVigueur() ?? 0,
        ]);
    }

    /**
     * Prix du litre en vigueur pour le carburant du véhicule choisi.
     *
     * Le pompiste ne saisit jamais ce prix : il n'a pas à le connaître, et le
     * laisser libre ouvrirait la porte aux écarts de valorisation entre deux
     * pleins du même jour.
     */
    private function prixEnVigueur(): ?float
    {
        $vehicule = Vehicule::query()->with('carburant')->find($this->input('vehicule_id'));

        return $vehicule?->carburant?->prix_par_defaut;
    }

    /**
     * En modification, le prix enregistré au moment du plein est conservé —
     * c'est un fait historique. Il n'est repris au tarif courant que si le
     * plein change de véhicule, donc potentiellement de carburant.
     */
    private function prixConserve(Sortie $sortie): float
    {
        $vehiculeInchange = (int) $this->input('vehicule_id') === $sortie->vehicule_id;

        return $vehiculeInchange
            ? $sortie->prix_unitaire
            : ($this->prixEnVigueur() ?? $sortie->prix_unitaire);
    }

    public function rules(): array
    {
        $vehicule = Vehicule::query()->find($this->input('vehicule_id'));
        $date = $this->jourDemande();

        $sortie = $this->route('sortie');
        $sortieId = $sortie instanceof Sortie ? $sortie->id : null;

        return [
            'date_sortie' => ['required', 'date'],
            // Le carburant d'une sortie se déduit du véhicule : il n'est ni
            // saisi, ni transmis.
            'vehicule_id' => ['required', 'integer', 'exists:vehicules,id'],
            'chauffeur_id' => ['required', 'integer', 'exists:chauffeurs,id'],
            'litres_servis' => [
                'required',
                'numeric',
                'gt:0',
                new LitresDansCapaciteReservoir($vehicule),
            ],
            // Posé par prepareForValidation, jamais par le poste de saisie.
            'prix_unitaire' => ['required', 'numeric', 'min:0'],
            'index_compteur' => [
                'required',
                'numeric',
                'min:0',
                new IndexCompteurCoherent(
                    $vehicule,
                    $date,
                    $sortieId,
                    app(ConsommationService::class),
                ),
            ],
            'index_pompe' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'date_sortie' => 'date',
            'vehicule_id' => 'véhicule',
            'chauffeur_id' => 'chauffeur',
            'litres_servis' => 'litres servis',
            'index_compteur' => 'index compteur',
            'index_pompe' => 'index pompe',
        ];
    }

    public function messages(): array
    {
        return [
            'litres_servis.gt' => 'Les litres servis doivent être supérieurs à zéro.',
            'vehicule_id.exists' => 'Ce véhicule n\'existe pas dans le référentiel.',
            'chauffeur_id.exists' => 'Ce chauffeur n\'existe pas dans le référentiel.',
        ];
    }

    /**
     * Instant de la sortie, à la seconde, ou null si la saisie est inexploitable.
     *
     * La chaîne de consommation s'ordonne désormais à la seconde : tronquer au
     * jour ramènerait tous les pleins d'une même journée à minuit et rendrait
     * leur ordre indéterminé.
     *
     * Les règles de validation sont construites avant que la donnée ne soit
     * validée : un horodatage illisible ne doit pas faire échouer la
     * construction des règles, seulement la règle « date » qui le signalera.
     */
    private function jourDemande(): ?string
    {
        $valeur = $this->input('date_sortie');

        if (! is_string($valeur) && ! $valeur instanceof \DateTimeInterface) {
            return null;
        }

        try {
            return Carbon::parse($valeur)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
