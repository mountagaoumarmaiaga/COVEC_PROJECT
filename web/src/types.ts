/** Contrats de l'API Laravel, recopiés depuis les ressources du backend. */

export type ModeSuivi = 'km' | 'heures'

export type RoleCode = 'pompiste' | 'gestionnaire' | 'consultation'

/** Compte d'accès à l'application, à ne pas confondre avec un chauffeur. */
export interface Utilisateur {
  id: number
  nom: string
  matricule: string
  role: RoleCode
  role_libelle: string
  role_description: string
  actif: boolean
  /**
   * Droits établis par le serveur. L'interface les lit pour masquer ce qui
   * serait refusé ; elle ne les recalcule jamais.
   */
  peut_servir: boolean
  peut_gerer: boolean
}

export interface RoleOption {
  valeur: RoleCode
  libelle: string
  description: string
}

/** Identité courte d'un carburant, telle qu'elle voyage avec les autres objets. */
export interface CarburantBref {
  id: number
  code: string
  libelle: string
  /** Prix du litre en vigueur : pré-remplit une livraison, s'applique à un plein. */
  prix_par_defaut: number
}

export interface Carburant extends CarburantBref {
  actif: boolean
  cuve?: {
    id: number
    nom: string
    capacite: number
  }
}

export interface Vehicule {
  id: number
  code: string
  designation: string
  mode_suivi: ModeSuivi
  mode_suivi_libelle: string
  /** « km » ou « h » : unité dans laquelle l'index compteur est relevé. */
  unite_index: string
  /** « L/100 km » ou « L/h » : unité de la consommation calculée. */
  unite_consommation: string
  capacite_reservoir: number
  carburant_id: number
  carburant?: CarburantBref
  actif: boolean
}

export interface Chauffeur {
  id: number
  nom: string
  matricule: string
  actif: boolean
}

/** Horodatage complet renvoyé par l'API, au format « AAAA-MM-JJ HH:MM:SS ». */
export type Horodatage = string

export interface Entree {
  id: number
  date_entree: Horodatage
  carburant_id: number
  carburant?: CarburantBref
  fournisseur: string
  quantite_litres: number
  prix_unitaire: number
  montant: number
  reference_bon: string | null
}

export interface Sortie {
  id: number
  date_sortie: Horodatage
  litres_servis: number
  /** Prix du litre au moment du plein, figé à l'enregistrement. */
  prix_unitaire: number
  montant: number
  index_compteur: number
  index_pompe: number | null
  /**
   * Nuls tant que le véhicule n'a pas deux pleins : le premier ne sert que
   * de repère au compteur, il n'y a rien à mesurer avant lui.
   */
  distance_parcourue: number | null
  consommation: number | null
  moyenne_reference: number | null
  ecart_pourcentage: number | null
  anomalie: boolean
  vehicule?: Vehicule
  chauffeur?: Chauffeur
}

/** Bilan d'une cuve : un carburant, un stock, un prix. */
export interface BilanCarburant {
  carburant: CarburantBref
  cuve: { id: number; nom: string; capacite: number }
  stock_actuel: number
  total_entrees: number
  total_sorties: number
  prix_moyen_pondere: number
  taux_remplissage: number | null
  nombre_pleins_anormaux: number
}

export interface TotauxCarburant {
  carburant: CarburantBref
  entrees: { nombre: number; litres: number; montant: number }
  sorties: { nombre: number; litres: number; montant: number; nombre_anomalies: number }
}

export interface TotauxMois {
  annee: number
  mois: number
  carburants: TotauxCarburant[]
  /**
   * Seuls les montants se totalisent toutes cuves confondues : additionner
   * des litres de gasoil et d'essence ne correspondrait à aucun réservoir.
   */
  ensemble: {
    entrees: { nombre: number; montant: number }
    sorties: { nombre: number; montant: number; nombre_anomalies: number }
  }
}

export interface LigneConsommation {
  vehicule: {
    id: number
    code: string
    designation: string
    mode_suivi: ModeSuivi
    unite_consommation: string
    unite_index: string
    carburant: CarburantBref | null
  }
  nombre_pleins: number
  litres_servis: number
  montant: number
  distance_totale: number
  moyenne_consommation: number | null
  dernier_index: number | null
  nombre_anomalies: number
}

export interface EcranStock {
  synthese: {
    carburants: BilanCarburant[]
    nombre_pleins_anormaux: number
  }
  totaux_mois: TotauxMois
  consommation_par_vehicule: LigneConsommation[]
  consommation_cumulee: LigneConsommation[]
}

/** Réponse paginée d'une collection de ressources Laravel. */
export interface Page<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}
