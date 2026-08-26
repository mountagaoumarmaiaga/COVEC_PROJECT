/** Mise en forme française des nombres, volumes, montants et dates. */

const nombre = new Intl.NumberFormat('fr-FR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
})

const nombreExact = new Intl.NumberFormat('fr-FR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const entier = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })

export function formaterNombre(valeur: number | null | undefined): string {
  return valeur === null || valeur === undefined ? '—' : nombre.format(valeur)
}

/**
 * Nombre sans décimale, pour les montants dont l'unité est portée à côté.
 *
 * Le franc CFA ne se subdivise pas en pratique : afficher des centimes sur
 * une facture de carburant donnerait une fausse impression de précision.
 */
export function formaterEntier(valeur: number | null | undefined): string {
  return valeur === null || valeur === undefined ? '—' : entier.format(valeur)
}

export function formaterLitres(valeur: number | null | undefined): string {
  return valeur === null || valeur === undefined ? '—' : `${nombreExact.format(valeur)} L`
}

/**
 * Montant en francs CFA. Le franc CFA ne se subdivise pas en pratique :
 * afficher des centimes sur une facture de carburant n'aurait pas de sens.
 */
export function formaterMontant(valeur: number | null | undefined): string {
  return valeur === null || valeur === undefined ? '—' : `${entier.format(valeur)} FCFA`
}

export function formaterConsommation(
  valeur: number | null | undefined,
  unite: string,
): string {
  return valeur === null || valeur === undefined ? '—' : `${nombre.format(valeur)} ${unite}`
}

export function formaterEcart(valeur: number | null | undefined): string {
  if (valeur === null || valeur === undefined) return '—'

  const signe = valeur > 0 ? '+' : ''

  return `${signe}${nombre.format(valeur)} %`
}

/*
  Les horodatages arrivent en texte, « AAAA-MM-JJ HH:MM:SS ». Ils sont
  découpés à la main plutôt que confiés à `new Date` : la construction d'un
  objet Date à partir d'une chaîne sans fuseau est traitée différemment selon
  les navigateurs, et un plein de 6 h du matin ne doit pas s'afficher la
  veille au soir.
*/

/** « 2026-08-25 14:37:12 » devient « 25/08/2026 ». */
export function formaterDate(horodatage: string | null | undefined): string {
  if (!horodatage) return '—'

  const [annee, mois, jour] = horodatage.slice(0, 10).split('-')

  return `${jour}/${mois}/${annee}`
}

/** « 2026-08-25 14:37:12 » devient « 14h37 ». */
export function formaterHeure(horodatage: string | null | undefined): string {
  if (!horodatage || horodatage.length < 16) return '—'

  return `${horodatage.slice(11, 13)}h${horodatage.slice(14, 16)}`
}

/** « 2026-08-25 14:37:12 » devient « 25/08/2026 à 14h37 ». */
export function formaterDateHeure(horodatage: string | null | undefined): string {
  if (!horodatage) return '—'

  return `${formaterDate(horodatage)} à ${formaterHeure(horodatage)}`
}

export const MOIS = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
] as const

export function nomMois(mois: number): string {
  return MOIS[mois - 1] ?? String(mois)
}

/**
 * Nom du mois précédé de « de », avec élision devant une voyelle.
 *
 * Trois mois commencent par une voyelle — avril, août, octobre — et
 * « totaux de août » se lit comme une faute dans un document de gestion.
 */
export function duMois(mois: number): string {
  const nom = nomMois(mois)

  return /^[aeiouâàéèêîôû]/i.test(nom) ? `d’${nom}` : `de ${nom}`
}

/**
 * Horodatage local au format attendu par un <input type="datetime-local">.
 *
 * Ne sert qu'à la correction d'un mouvement déjà enregistré : à la création,
 * l'instant est posé par le serveur.
 */
export function maintenantLocal(): string {
  const maintenant = new Date()
  const decalage = maintenant.getTimezoneOffset() * 60_000

  return new Date(maintenant.getTime() - decalage).toISOString().slice(0, 16)
}

/** « 2026-08-25 14:37:12 » vers la valeur d'un <input type="datetime-local">. */
export function versChampHorodatage(horodatage: string): string {
  return horodatage.slice(0, 16).replace(' ', 'T')
}

/** Valeur d'un <input type="datetime-local"> vers le format attendu par l'API. */
export function depuisChampHorodatage(valeur: string): string {
  return `${valeur.replace('T', ' ')}:00`.slice(0, 19)
}
