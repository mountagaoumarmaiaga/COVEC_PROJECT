<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Carburant;
use App\Models\Cuve;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
     * clé.
     *
     * Aucun mot de passe n'est écrit dans ce fichier. Un mot de passe par
     * défaut dans le code source vaut mot de passe public : il est lisible
     * par quiconque accède au dépôt, et il reste valable sur l'installation
     * déployée tant que personne ne le change — ce que personne ne fait.
     * À défaut de COVEC_ADMIN_PASSWORD, il est donc tiré au hasard et affiché
     * une seule fois, ici même.
     */
    private function premierGestionnaire(): void
    {
        $motDePasse = trim((string) env('COVEC_ADMIN_PASSWORD', ''));
        $tireAuHasard = $motDePasse === '';

        if ($tireAuHasard) {
            // Sans symboles : il se tape sur le clavier d'un poste de station,
            // parfois sous la pluie, et doit pouvoir être dicté au téléphone.
            $motDePasse = Str::password(14, symbols: false);
        }

        $compte = User::query()->firstOrCreate(
            ['matricule' => 'ADMIN'],
            [
                'nom' => 'Gestionnaire du dépôt',
                'role' => Role::Gestionnaire,
                'password' => $motDePasse,
                'actif' => true,
            ],
        );

        if (! $compte->wasRecentlyCreated) {
            return;
        }

        if ($tireAuHasard) {
            $this->command?->warn(sprintf(
                'Compte gestionnaire créé — matricule ADMIN, mot de passe « %s ».',
                $motDePasse,
            ));
            $this->command?->warn('Notez-le maintenant : il n’est affiché qu’une fois et n’est stocké nulle part en clair.');

            return;
        }

        $this->command?->warn('Compte gestionnaire créé — matricule ADMIN, mot de passe repris de COVEC_ADMIN_PASSWORD.');
    }
}
