<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rôles d'accès à l'application.
 *
 * Le découpage suit la séparation des tâches d'un dépôt : celui qui sert le
 * carburant n'est pas celui qui corrige l'historique. Un pompiste enregistre
 * ses pleins mais ne peut ni les modifier ni les supprimer — sinon les trois
 * contrôles du §5 ne protégeraient plus rien, puisqu'une saisie refusée
 * pourrait être contournée en corrigeant la précédente.
 */
enum Role: string
{
    case Pompiste = 'pompiste';
    case Gestionnaire = 'gestionnaire';
    case Consultation = 'consultation';

    public function libelle(): string
    {
        return match ($this) {
            self::Pompiste => 'Pompiste',
            self::Gestionnaire => 'Gestionnaire',
            self::Consultation => 'Consultation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pompiste => 'Enregistre les pleins et consulte le stock.',
            self::Gestionnaire => 'Accès complet : référentiel, livraisons, corrections, comptes.',
            self::Consultation => 'Lecture seule et export Excel.',
        };
    }

    /** Peut enregistrer un plein servi à un véhicule. */
    public function peutServir(): bool
    {
        return $this === self::Pompiste || $this === self::Gestionnaire;
    }

    /**
     * Peut corriger ou supprimer un mouvement, tenir le référentiel et gérer
     * les comptes. Réservé au gestionnaire.
     */
    public function peutGerer(): bool
    {
        return $this === self::Gestionnaire;
    }

    /** @return array<int, array{valeur: string, libelle: string, description: string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => [
            'valeur' => $r->value,
            'libelle' => $r->libelle(),
            'description' => $r->description(),
        ], self::cases());
    }
}
