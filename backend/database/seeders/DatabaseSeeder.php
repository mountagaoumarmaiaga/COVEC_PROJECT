<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ModeSuivi;
use App\Enums\Role;
use App\Models\Carburant;
use App\Models\Chauffeur;
use App\Models\Cuve;
use App\Models\Entree;
use App\Models\Sortie;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\ConsommationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Jeu de démonstration : deux mois d'exploitation d'une station COVEC.
 *
 * Deux carburants, deux cuves. Les pleins sont générés à partir d'une
 * consommation cible par véhicule, avec une variation faible, pour que les
 * moyennes soient crédibles. Deux pleins volontairement excessifs sont
 * injectés afin que l'écran de suivi montre dès la première ouverture à quoi
 * ressemble un plein signalé en rouge.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ce jeu crée trois comptes dont le mot de passe est écrit dans ce
        // fichier : il n'a rien à faire sur une installation réelle, et une
        // commande lancée dans le mauvais terminal suffirait à l'y poser.
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Jeu de démonstration refusé en production. Utilisez ReferentielSeeder.',
            );
        }

        // Tirage figé : la démonstration doit être identique à chaque
        // réinstallation, sinon les captures d'écran ne correspondent plus.
        mt_srand(20260825);

        $this->comptes();
        $carburants = $this->carburants();

        $this->livraisons($carburants);
        $chauffeurs = $this->chauffeurs();
        $parc = $this->vehicules($carburants);

        $this->pleins($parc, $chauffeurs);

        $consommation = app(ConsommationService::class);

        foreach ($parc as ['modele' => $vehicule]) {
            $consommation->recalculerChaine($vehicule);
        }
    }

    /** Un compte par rôle, pour que la démonstration montre les trois vues. */
    private function comptes(): void
    {
        $comptes = [
            ['Gestionnaire du dépôt', 'ADMIN', Role::Gestionnaire],
            ['Pompiste de service', 'POMPE-01', Role::Pompiste],
            ['Direction', 'DIR-01', Role::Consultation],
        ];

        foreach ($comptes as [$nom, $matricule, $role]) {
            User::query()->create([
                'nom' => $nom,
                'matricule' => $matricule,
                'role' => $role,
                'password' => 'covec2026',
                'actif' => true,
            ]);
        }

        $this->command?->warn('Comptes de démonstration : ADMIN, POMPE-01, DIR-01 — mot de passe « covec2026 ».');
    }

    /**
     * Les deux carburants et leur cuve.
     *
     * @return array<string, Carburant>
     */
    private function carburants(): array
    {
        $definitions = [
            ['gasoil', 'Gasoil', 945, 'Cuve gasoil — dépôt COVEC', 20000],
            ['essence', 'Essence', 875, 'Cuve essence — dépôt COVEC', 5000],
        ];

        $carburants = [];

        foreach ($definitions as [$code, $libelle, $prix, $nomCuve, $capacite]) {
            $carburant = Carburant::query()->create([
                'code' => $code,
                'libelle' => $libelle,
                'prix_par_defaut' => $prix,
                'actif' => true,
            ]);

            Cuve::query()->create([
                'carburant_id' => $carburant->id,
                'nom' => $nomCuve,
                'capacite' => $capacite,
            ]);

            $carburants[$code] = $carburant;
        }

        return $carburants;
    }

    /** @param  array<string, Carburant>  $carburants */
    private function livraisons(array $carburants): void
    {
        $debut = Carbon::now()->startOfMonth()->subMonth();

        // On ne livre que ce que la cuve peut encore absorber. Le parc gasoil
        // consomme environ 10 000 L sur les deux mois pour 20 000 L de cuve,
        // le parc essence environ 1 200 L pour 5 000 L.
        $livraisons = [
            ['gasoil', 0, 12000, 930, 'Total Énergies Mali', 'BL-2026-0714'],
            ['essence', 3, 2000, 862, 'Ola Énergie', 'BL-2026-0718'],
            ['gasoil', 18, 5000, 941, 'Ola Énergie', 'BL-2026-0801'],
            ['gasoil', 34, 5000, 945, 'Total Énergies Mali', 'BL-2026-0855'],
            ['essence', 38, 1500, 875, 'Vivo Energy', 'BL-2026-0861'],
        ];

        foreach ($livraisons as [$code, $jour, $litres, $prix, $fournisseur, $bon]) {
            Entree::query()->create([
                'date_entree' => $debut->copy()->addDays($jour)->setTime(mt_rand(8, 11), mt_rand(0, 59)),
                'carburant_id' => $carburants[$code]->id,
                'fournisseur' => $fournisseur,
                'quantite_litres' => $litres,
                'prix_unitaire' => $prix,
                'reference_bon' => $bon,
            ]);
        }
    }

    /** @return Collection<int, Chauffeur> */
    private function chauffeurs(): Collection
    {
        $noms = [
            ['Amadou Traoré', 'CH-001'],
            ['Fatoumata Diarra', 'CH-002'],
            ['Ibrahim Coulibaly', 'CH-003'],
            ['Seydou Keïta', 'CH-004'],
            ['Mariam Sidibé', 'CH-005'],
            ['Boubacar Cissé', 'CH-006'],
        ];

        return collect($noms)->map(fn (array $c) => Chauffeur::query()->create([
            'nom' => $c[0],
            'matricule' => $c[1],
            'actif' => true,
        ]));
    }

    /**
     * Le parc, chaque véhicule accompagné de sa consommation cible.
     *
     * La cible ne fait pas partie du modèle métier : elle ne sert qu'à
     * fabriquer des pleins vraisemblables.
     *
     * @param  array<string, Carburant>  $carburants
     * @return Collection<int, array{modele: Vehicule, cible: float}>
     */
    private function vehicules(array $carburants): Collection
    {
        // Cible : L/100 km en mode kilométrage, L/h en mode heures.
        $parc = [
            ['VL-001', 'Toyota Hilux double cabine', 'gasoil', ModeSuivi::Kilometrage, 80, 11.5],
            ['VL-002', 'Toyota Land Cruiser station', 'essence', ModeSuivi::Kilometrage, 90, 14.5],
            ['VL-003', 'Toyota Corolla — véhicule de liaison', 'essence', ModeSuivi::Kilometrage, 50, 8.2],
            ['CM-001', 'Mercedes Actros — benne 20 t', 'gasoil', ModeSuivi::Kilometrage, 300, 34.0],
            ['CM-002', 'Renault Kerax — citerne à eau', 'gasoil', ModeSuivi::Kilometrage, 400, 38.5],
            ['EN-001', 'Pelle hydraulique Caterpillar 320', 'gasoil', ModeSuivi::Heures, 400, 18.0],
            ['EN-002', 'Chargeuse Komatsu WA320', 'gasoil', ModeSuivi::Heures, 280, 15.5],
            ['GE-001', 'Groupe électrogène 250 kVA', 'gasoil', ModeSuivi::Heures, 400, 42.0],
        ];

        return collect($parc)->map(fn (array $v) => [
            'modele' => Vehicule::query()->create([
                'code' => $v[0],
                'designation' => $v[1],
                'carburant_id' => $carburants[$v[2]]->id,
                'mode_suivi' => $v[3],
                'capacite_reservoir' => $v[4],
                'actif' => true,
            ]),
            'cible' => (float) $v[5],
        ]);
    }

    /**
     * @param  Collection<int, array{modele: Vehicule, cible: float}>  $parc
     * @param  Collection<int, Chauffeur>  $chauffeurs
     */
    private function pleins(Collection $parc, Collection $chauffeurs): void
    {
        $debut = Carbon::now()->startOfMonth()->subMonth();

        foreach ($parc as $rang => ['modele' => $vehicule, 'cible' => $cible]) {
            $kilometrage = $vehicule->mode_suivi === ModeSuivi::Kilometrage;

            // Index de départ : un compteur de véhicule en service n'est
            // jamais à zéro, et l'écart entre engins rend l'écran plus lisible.
            $index = $kilometrage
                ? 40000 + $rang * 12500
                : 1200 + $rang * 340;

            $jour = 1 + $rang;

            // Le plein signalé en rouge tombe au milieu de la série, une fois
            // que le véhicule a assez d'historique pour avoir une moyenne.
            $rangAnomalie = ($rang === 3 || $rang === 6) ? 5 : null;

            for ($n = 0; $n < 9; $n++) {
                $usage = $kilometrage
                    ? mt_rand(380, 620)          // kilomètres entre deux pleins
                    : mt_rand(7, 13);            // heures moteur entre deux pleins

                $variation = mt_rand(-6, 6) / 100;
                $facteur = $n === $rangAnomalie ? 1.55 : 1 + $variation;

                $litres = $kilometrage
                    ? $cible * $usage / 100 * $facteur
                    : $cible * $usage * $facteur;

                // Le contrôle n°2 refuserait un plein plus gros que le
                // réservoir : le jeu de démonstration doit rester saisissable.
                $litres = min(round($litres, 2), $vehicule->capacite_reservoir);

                $index += $usage;

                Sortie::query()->create([
                    // Les pleins se font aux heures d'ouverture de la station.
                    'date_sortie' => $debut->copy()->addDays($jour)->setTime(mt_rand(6, 17), mt_rand(0, 59)),
                    'vehicule_id' => $vehicule->id,
                    'chauffeur_id' => $chauffeurs[($rang + $n) % $chauffeurs->count()]->id,
                    'litres_servis' => $litres,
                    'prix_unitaire' => $vehicule->carburant->prix_par_defaut,
                    'index_compteur' => $index,
                ]);

                $jour += mt_rand(4, 8);
            }
        }
    }
}
