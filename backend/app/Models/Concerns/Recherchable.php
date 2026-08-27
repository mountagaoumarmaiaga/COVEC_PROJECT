<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche textuelle sur quelques colonnes d'un modèle.
 *
 * Le terme saisi est cherché dans chacune des colonnes déclarées ; une seule
 * correspondance suffit. Un terme vide ne filtre rien, ce qui permet de
 * brancher le scope sans condition dans un contrôleur.
 */
trait Recherchable
{
    /**
     * Les colonnes fouillées par la recherche.
     *
     * @return array<int, string>
     */
    abstract protected function colonnesRecherchees(): array;

    public function scopeRecherche(Builder $query, ?string $terme): Builder
    {
        $terme = trim((string) $terme);

        if ($terme === '') {
            return $query;
        }

        $motif = '%'.mb_strtolower($terme).'%';

        return $query->where(function (Builder $q) use ($motif) {
            foreach ($this->colonnesRecherchees() as $colonne) {
                // « lower() » des deux côtés, plutôt que « like » seul :
                // PostgreSQL distingue la casse, SQLite non. Sans cette
                // précaution, chercher « hilux » fonctionnerait sur le poste
                // du développeur et ne trouverait rien en production.
                $q->orWhereRaw('lower('.$colonne.') like ?', [$motif]);
            }
        });
    }
}
