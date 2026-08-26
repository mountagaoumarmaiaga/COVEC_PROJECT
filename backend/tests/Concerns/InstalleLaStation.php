<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\ModeSuivi;
use App\Enums\Role;
use App\Models\Carburant;
use App\Models\Chauffeur;
use App\Models\Cuve;
use App\Models\User;
use App\Models\Vehicule;

/**
 * Référentiel minimal commun aux tests : un carburant, sa cuve, un chauffeur.
 *
 * Chaque test part d'une station vide ; sans ce socle il faudrait recopier la
 * même installation dans chaque fichier, et une évolution du référentiel
 * demanderait de la corriger partout.
 */
trait InstalleLaStation
{
    protected Carburant $gasoil;

    protected Chauffeur $chauffeur;

    protected User $gestionnaire;

    protected function installerLaStation(float $capaciteCuve = 20000, float $prix = 945): void
    {
        $this->gasoil = Carburant::query()->create([
            'code' => 'gasoil',
            'libelle' => 'Gasoil',
            'prix_par_defaut' => $prix,
        ]);

        Cuve::query()->create([
            'carburant_id' => $this->gasoil->id,
            'nom' => 'Cuve gasoil',
            'capacite' => $capaciteCuve,
        ]);

        $this->chauffeur = Chauffeur::query()->create([
            'nom' => 'Amadou Traoré',
            'matricule' => 'CH-001',
        ]);

        // Toutes les routes sont derrière l'authentification : sans compte
        // connecté, chaque test tomberait sur un 401 avant d'atteindre la
        // règle métier qu'il cherche à vérifier.
        $this->gestionnaire = $this->compte(Role::Gestionnaire, 'ADMIN');
        $this->actingAs($this->gestionnaire, 'sanctum');
    }

    protected function compte(Role $role, string $matricule): User
    {
        return User::query()->create([
            'nom' => $role->libelle(),
            'matricule' => $matricule,
            'role' => $role,
            'password' => 'motdepasse1',
            'actif' => true,
        ]);
    }

    protected function carburant(string $code, string $libelle, float $prix, float $capacite): Carburant
    {
        $carburant = Carburant::query()->create([
            'code' => $code,
            'libelle' => $libelle,
            'prix_par_defaut' => $prix,
        ]);

        Cuve::query()->create([
            'carburant_id' => $carburant->id,
            'nom' => 'Cuve '.$libelle,
            'capacite' => $capacite,
        ]);

        return $carburant;
    }

    protected function vehicule(
        ModeSuivi $mode = ModeSuivi::Kilometrage,
        float $capacite = 80,
        ?Carburant $carburant = null,
    ): Vehicule {
        return Vehicule::query()->create([
            'code' => 'TST-'.fake()->unique()->numberBetween(100, 999),
            'designation' => 'Véhicule de test',
            'carburant_id' => ($carburant ?? $this->gasoil)->id,
            'mode_suivi' => $mode,
            'capacite_reservoir' => $capacite,
        ]);
    }

    /**
     * Sert un plein par l'API.
     *
     * Ni la date ni le prix ne sont transmis : le serveur les pose lui-même.
     *
     * @param  array<string, mixed>  $attributs
     */
    protected function servir(Vehicule $vehicule, array $attributs): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/sorties', array_merge([
            'vehicule_id' => $vehicule->id,
            'chauffeur_id' => $this->chauffeur->id,
        ], $attributs));
    }
}
