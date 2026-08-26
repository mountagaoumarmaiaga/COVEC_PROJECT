<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Messages de validation
|--------------------------------------------------------------------------
|
| Traduction des règles effectivement utilisées par l'application. Les
| messages remontent tels quels à l'écran de saisie de la station : ils
| s'adressent au pompiste, pas au développeur.
|
*/

return [
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'date' => 'Le champ :attribute n\'est pas une date valide.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'enum' => 'La valeur du champ :attribute n\'est pas autorisée.',
    'exists' => 'La valeur sélectionnée pour :attribute n\'existe pas.',
    'gt' => [
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'gte' => [
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
    ],
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'lt' => [
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
    ],
    'lte' => [
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
    ],
    'max' => [
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'capacite' => 'capacité',
        'capacite_reservoir' => 'capacité du réservoir',
        'chauffeur_id' => 'chauffeur',
        'code' => 'code interne',
        'date_entree' => 'date',
        'date_sortie' => 'date',
        'designation' => 'désignation',
        'fournisseur' => 'fournisseur',
        'index_compteur' => 'index compteur',
        'index_pompe' => 'index pompe',
        'litres_servis' => 'litres servis',
        'matricule' => 'matricule',
        'mode_suivi' => 'mode de suivi',
        'nom' => 'nom',
        'prix_unitaire' => 'prix unitaire',
        'quantite_litres' => 'quantité en litres',
        'reference_bon' => 'référence du bon',
        'vehicule_id' => 'véhicule',
    ],
];
