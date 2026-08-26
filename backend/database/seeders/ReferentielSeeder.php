<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Carburant;
use App\Models\Cuve;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Amorçage d'une station qui démarre pour de bon.
 *
 * Ne pose que ce qui est connu avec certitude : les deux carburants
 * distribués et leur prix. Le parc, les chauffeurs et les capacités de cuve
 * sont propres à la station — ils se saisissent depuis le référentiel, et les
 * inventer ici reviendrait à livrer des données fausses qu'il faudrait
 * ensuite retrouver et corriger.
 *
 * Aucun mouvement : les compteurs partent de zéro.
 *
 *     php artisan migrate:fresh --seed --seeder=Database\\Seeders\\ReferentielSeeder
 */
class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $carburants = [
            ['gasoil', 'Gasoil', 945],
            ['essence', 'Essence', 875],
        ];

        foreach ($carburants as [$code, $libelle, $prix]) {
            $carburant = Carburant::query()->updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'prix_par_defaut' => $prix, 'actif' => true],
            );

            // Capacité laissée à zéro : elle est propre à la cuve installée sur
            // le site. L'écran de stock affiche alors « renseignez la capacité »
            // au lieu d'un taux de remplissage inventé.
            Cuve::query()->firstOrCreate(
                ['carburant_id' => $carburant->id],
                ['nom' => 'Cuve '.mb_strtolower($libelle), 'capacite' => 0],
            );
        }

        $this->premierGestionnaire();

        $this->command?->info('Référentiel posé : gasoil 945 F, essence 875 F. Capacités de cuve, parc et chauffeurs à saisir depuis l\'interface.');
    }

    /**
     * Le compte qui permet d'entrer la première fois.
     *
     * Sans lui, l'application serait fermée à clé sans que personne ait la
     * clé. Le mot de passe est volontairement banal et doit être changé à la
     * première connexion.
     */
    private function premierGestionnaire(): void
    {
        $motDePasse = env('COVEC_ADMIN_PASSWORD', 'covec2026');

        $compte = User::query()->firstOrCreate(
            ['matricule' => 'ADMIN'],
            [
                'nom' => 'Gestionnaire du dépôt',
                'role' => Role::Gestionnaire,
                'password' => $motDePasse,
                'actif' => true,
            ],
        );

        if ($compte->wasRecentlyCreated) {
            $this->command?->warn(sprintf(
                'Compte gestionnaire créé — matricule ADMIN, mot de passe « %s ». À CHANGER à la première connexion.',
                $motDePasse,
            ));
        }
    }
}
