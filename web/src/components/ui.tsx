import { AlertTriangle, Search, X } from 'lucide-react'
import type { ButtonHTMLAttributes, ComponentProps, InputHTMLAttributes, ReactNode } from 'react'

import { Selecteur } from './Selecteur'

export type { OptionListe } from './Selecteur'
export { Selecteur }

/*
  Vocabulaire commun aux six écrans.

  Le principe qui décide de presque tout ici : un filet plutôt qu'un cadre.
  Une section se sépare de la suivante par un trait d'un pixel, pas par une
  carte posée sur un fond gris. Seuls les formulaires de saisie s'élèvent du
  papier, parce qu'ils demandent une action.
*/

/** Capitales interlettrées qui coiffent un titre ou une valeur. */
export function Surtitre({
  children,
  ton = 'attenue',
  className = '',
}: {
  children: ReactNode
  ton?: 'attenue' | 'or' | 'pale'
  className?: string
}) {
  const tons = {
    attenue: 'text-attenue',
    or: 'text-kinpaku-profond',
    pale: 'text-pale',
  }

  return <p className={`surtitre ${tons[ton]} ${className}`}>{children}</p>
}

/** En-tête d'écran : surtitre, titre en graisse fine, phrase d'explication. */
export function EnteteEcran({
  surtitre,
  titre,
  children,
}: {
  surtitre?: string
  titre: string
  children?: ReactNode
}) {
  return (
    <header>
      {surtitre && <Surtitre ton="or" className="mb-3.5">{surtitre}</Surtitre>}
      <h1 className="font-display text-[clamp(2.6rem,5vw,4.5rem)] leading-[1.02] font-extralight tracking-[-0.01em]">
        {titre}
      </h1>
      {children && (
        <p className="mt-4 max-w-[62ch] text-[15px] leading-[1.8] text-pretty text-attenue">
          {children}
        </p>
      )}
    </header>
  )
}

/**
 * Section de contenu.
 *
 * Par défaut elle ne porte aucun cadre : son titre est souligné d'un filet et
 * le contenu suit. `levee` la pose sur une surface claire avec une ombre
 * discrète — réservé aux formulaires de saisie.
 */
export function Carte({
  titre,
  description,
  actions,
  levee = false,
  children,
  className = '',
}: {
  titre?: string
  description?: string
  actions?: ReactNode
  levee?: boolean
  children: ReactNode
  className?: string
}) {
  const enveloppe = levee
    ? 'bg-leve border border-filet shadow-leve rounded-net'
    : 'bg-transparent'

  return (
    <section className={`${enveloppe} ${className}`}>
      {(titre || actions) && (
        <header
          className={`flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3 ${
            levee ? 'px-8 pt-7 pb-1' : 'pb-3'
          }`}
        >
          <div>
            {titre && (
              <h2 className="font-display text-[30px] leading-tight font-light">{titre}</h2>
            )}
            {description && (
              <p className="mt-1 max-w-[78ch] text-[13px] text-attenue">{description}</p>
            )}
          </div>
          {actions && (
            <div className="flex flex-wrap items-baseline gap-x-5 gap-y-2">{actions}</div>
          )}
        </header>
      )}
      <div className={levee ? 'px-8 pt-5 pb-8' : ''}>{children}</div>
    </section>
  )
}

type VarianteBouton =
  | 'primaire'
  | 'secondaire'
  | 'discret'
  | 'danger'
  | 'icone'
  | 'icone-danger'

/*
  Un bouton doit se voir avant d'être lu.

  La première version poussait la retenue trop loin : « discret » n'avait ni
  fond ni contour, si bien que les icônes de modification et de suppression
  d'une ligne ressemblaient à de la décoration. Sur un poste de station, où
  l'on cherche vite, une commande invisible est une commande perdue.

  Chaque variante porte donc une bordure ou un aplat. La retenue se joue sur
  l'intensité — filet contre arête, voile contre aplat — pas sur l'absence.
*/

// Zone tactile portée au-delà de la cible visible : un doigt vise moins bien
// qu'un curseur, et agrandir le bouton lui-même alourdirait chaque ligne.
const DEBORD = "after:absolute after:-inset-1 after:content-['']"

const VARIANTES: Record<VarianteBouton, string> = {
  // Action principale de l'écran : pleine et sombre, repérable au premier
  // coup d'oeil parmi des filets clairs.
  primaire: 'h-11 px-6 bg-encre text-papier hover:bg-black focus-visible:outline-encre',

  // Action secondaire : contour franc — l'arête, pas le filet — pour se
  // détacher du texte voisin.
  secondaire:
    'h-11 px-5 border border-arete bg-leve text-encre hover:border-encre hover:bg-papier-profond focus-visible:outline-arete',

  // Retenue, mais lisible : fond légèrement appuyé et contour au filet.
  discret: `h-10 px-4 border border-filet bg-papier-profond text-attenue hover:border-arete hover:text-encre focus-visible:outline-arete ${DEBORD}`,

  // Le vermillon est réservé à ce qui ne se rattrape pas.
  danger: `h-10 px-4 border border-vermillon-trait bg-vermillon-voile text-vermillon hover:border-vermillon focus-visible:outline-vermillon ${DEBORD}`,

  // Icône seule, dans une ligne de tableau : carré cerné, jamais un glyphe nu.
  icone: `size-10 border border-filet bg-papier-profond text-attenue hover:border-arete hover:text-encre focus-visible:outline-arete ${DEBORD}`,

  // Même carré, teinté : supprimer ne doit pas ressembler à modifier.
  'icone-danger': `size-10 border border-vermillon-trait bg-vermillon-voile text-vermillon hover:border-vermillon focus-visible:outline-vermillon ${DEBORD}`,
}

export function Bouton({
  variante = 'primaire',
  className = '',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variante?: VarianteBouton }) {
  return (
    <button
      {...props}
      className={`relative inline-flex cursor-pointer items-center justify-center gap-2 rounded-net text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-45 ${VARIANTES[variante]} ${className}`}
    />
  )
}

/** Lien d'action dans une barre de filtres ou un pied de formulaire. */
export function LienAction({
  actif = false,
  className = '',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { actif?: boolean }) {
  return (
    <button
      type="button"
      {...props}
      className={`relative cursor-pointer pb-1 text-[13px] transition-colors after:absolute after:inset-x-0 after:-inset-y-2.5 after:content-[''] ${
        actif
          ? 'border-b border-kinpaku text-encre'
          : 'border-b border-transparent text-patine-profonde hover:text-patine'
      } ${className}`}
    />
  )
}

export function Champ({
  label,
  erreurs,
  obligatoire,
  indication,
  children,
}: {
  label: string
  erreurs?: string[]
  obligatoire?: boolean
  indication?: ReactNode
  children: ReactNode
}) {
  const enFaute = Boolean(erreurs?.length)

  return (
    <label className="block">
      {/* Encre pleine, et non atténuée : à 11 px en capitales interlettrées,
          c'est la seule indication de ce qu'il faut taper. Les en-têtes de
          colonne, eux, restent en gris — ils accompagnent une donnée déjà
          lisible. */}
      <span className={`surtitre mb-1 block ${enFaute ? 'text-vermillon' : 'text-encre'}`}>
        {label}
        {obligatoire && <span className="ml-1 text-vermillon">*</span>}
      </span>
      {children}
      {indication && !enFaute && (
        <span className="mt-1.5 block text-xs text-pale">{indication}</span>
      )}
      {erreurs?.map((erreur) => (
        <span key={erreur} className="mt-1.5 block text-xs font-medium text-vermillon">
          {erreur}
        </span>
      ))}
    </label>
  )
}

export function Saisie({ className = '', ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`champ chiffres ${className}`} />
}

type PropsListe = Omit<ComponentProps<typeof Selecteur>, 'compact'>

/** Liste déroulante d'un formulaire. */
export function Liste(props: PropsListe) {
  return <Selecteur {...props} />
}

/** Variante compacte pour les barres de filtres au-dessus d'un tableau. */
export function ListeFiltre(props: PropsListe) {
  return <Selecteur compact {...props} />
}

export function Badge({
  ton = 'neutre',
  children,
}: {
  ton?: 'neutre' | 'signale' | 'or' | 'vert'
  children: ReactNode
}) {
  const tons = {
    neutre: 'border-filet text-attenue',
    signale: 'border-vermillon-trait text-vermillon font-medium',
    or: 'border-kinpaku-pale text-kinpaku-profond',
    vert: 'border-patine text-patine-profonde',
  }

  return (
    <span
      className={`chiffres inline-flex items-center gap-1.5 rounded-net border px-2.5 py-0.5 text-xs ${tons[ton]}`}
    >
      {children}
    </span>
  )
}

/**
 * Chiffre d'affichage.
 *
 * L'unité reste attachée à la valeur mais nettement plus petite : c'est le
 * nombre qu'on lit à distance, pas le « L » qui le suit.
 */
export function Chiffre({
  valeur,
  unite,
  taille = 'moyen',
  ton = 'encre',
}: {
  valeur: string
  unite?: string
  taille?: 'geant' | 'duo' | 'grand' | 'moyen' | 'petit'
  ton?: 'encre' | 'or' | 'signale'
}) {
  const tailles = {
    geant: { valeur: 'text-[clamp(4rem,9vw,9.25rem)] leading-[0.86]', unite: 'text-[0.28em]' },
    // « duo » : deux chiffres héros côte à côte, un par cuve. Le clamp les
    // empêche de se toucher quand la fenêtre se resserre.
    duo: { valeur: 'text-[clamp(2.75rem,5.5vw,6rem)] leading-[0.88]', unite: 'text-[0.3em]' },
    grand: { valeur: 'text-[42px] leading-none', unite: 'text-[0.45em]' },
    moyen: { valeur: 'text-[34px] leading-none', unite: 'text-[0.5em]' },
    petit: { valeur: 'text-[21px] leading-none', unite: 'text-[0.57em]' },
  }

  const tons = {
    encre: 'text-encre',
    or: 'text-kinpaku-profond',
    signale: 'text-vermillon',
  }

  const poids =
    taille === 'geant' || taille === 'duo'
      ? 'font-thin'
      : taille === 'grand'
        ? 'font-extralight'
        : 'font-light'

  return (
    <p
      className={`chiffres font-display m-0 tracking-[-0.015em] ${tailles[taille].valeur} ${poids} ${tons[ton]}`}
    >
      {valeur}
      {unite && (
        <span className={`ml-1.5 font-sans font-normal text-attenue ${tailles[taille].unite}`}>
          {unite}
        </span>
      )}
    </p>
  )
}

/** Bandeau des refus de saisie renvoyés par l'API — contrôles §5. */
export function AlerteErreurs({ messages }: { messages: string[] }) {
  if (messages.length === 0) return null

  return (
    <div
      role="alert"
      className="flex gap-3 border-l-2 border-vermillon bg-vermillon-voile px-4 py-3.5"
    >
      <AlertTriangle className="mt-0.5 size-4 shrink-0 text-vermillon" aria-hidden />
      <div>
        <p className="text-sm font-medium text-vermillon">Saisie refusée</p>
        <ul className="mt-1 space-y-1">
          {messages.map((message) => (
            <li key={message} className="chiffres text-sm leading-relaxed text-encre">
              {message}
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}

/**
 * Marche à suivre quand un écran ne peut pas encore servir.
 *
 * Une station qui démarre a un référentiel vide : arriver sur un formulaire
 * dont toutes les listes sont vides, sans un mot d'explication, laisse
 * l'utilisateur chercher ce qu'il a mal fait.
 */
export function Guidage({
  titre,
  children,
  action,
}: {
  titre: string
  children: ReactNode
  action?: ReactNode
}) {
  return (
    <div className="border-l-2 border-kinpaku bg-kinpaku-pale/20 px-5 py-4">
      <p className="text-sm font-medium text-encre">{titre}</p>
      <p className="mt-1 max-w-[70ch] text-sm leading-relaxed text-attenue">{children}</p>
      {action && <div className="mt-3">{action}</div>}
    </div>
  )
}

/** Barre de recherche d'une liste. */
export function Recherche({
  valeur,
  onChange,
  placeholder = 'Rechercher…',
  className = '',
}: {
  valeur: string
  onChange: (valeur: string) => void
  placeholder?: string
  className?: string
}) {
  return (
    <div className={`relative ${className}`}>
      <Search
        className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-pale"
        aria-hidden
      />
      <input
        type="search"
        value={valeur}
        onChange={(evenement) => onChange(evenement.target.value)}
        placeholder={placeholder}
        aria-label={placeholder}
        className="champ w-full pl-9"
      />
      {valeur !== '' && (
        <button
          type="button"
          onClick={() => onChange('')}
          aria-label="Effacer la recherche"
          className="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer p-1.5 text-pale transition-colors hover:text-encre"
        >
          <X className="size-3.5" aria-hidden />
        </button>
      )}
    </div>
  )
}

/** Ce que le serveur dit d'une page de résultats. */
export interface MetaPage {
  current_page: number
  last_page: number
  total: number
  from: number | null
  to: number | null
}

/**
 * Pied de liste : la position dans l'ensemble, et de quoi se déplacer.
 *
 * Rien ne s'affiche tant qu'il n'y a qu'une page. Un dépôt qui démarre n'a pas
 * à voir des commandes de navigation qui ne mènent nulle part.
 */
export function Pagination({
  meta,
  onPage,
}: {
  meta: MetaPage
  onPage: (page: number) => void
}) {
  if (meta.last_page <= 1) {
    return null
  }

  return (
    <nav
      aria-label="Pagination"
      className="mt-5 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-filet pt-4"
    >
      <p className="chiffres text-xs text-pale">
        {meta.from ?? 0}–{meta.to ?? 0} sur {meta.total}
      </p>

      <div className="flex items-center gap-6">
        <LienAction
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
        >
          Précédent
        </LienAction>

        <span className="surtitre text-attenue">
          {meta.current_page} / {meta.last_page}
        </span>

        <LienAction
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Suivant
        </LienAction>
      </div>
    </nav>
  )
}

export function EtatVide({ message }: { message: string }) {
  return (
    <p className="border-t border-filet py-14 text-center text-sm text-pale">{message}</p>
  )
}

export function Chargement() {
  return <p className="border-t border-filet py-14 text-center text-sm text-pale">Chargement…</p>
}

/** Le défilement horizontal reste dans le tableau, jamais dans la page. */
export function Tableau({ children }: { children: ReactNode }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[46rem] border-collapse text-sm">{children}</table>
    </div>
  )
}

export function EnteteColonne({
  children,
  aligne = 'gauche',
}: {
  children: ReactNode
  aligne?: 'gauche' | 'droite'
}) {
  return (
    <th
      scope="col"
      className={`surtitre border-b border-arete pb-2.5 font-normal whitespace-nowrap text-attenue ${
        aligne === 'droite' ? 'text-right' : 'text-left'
      }`}
    >
      {children}
    </th>
  )
}

export function Cellule({
  children,
  aligne = 'gauche',
  derniere = false,
  className = '',
}: {
  children: ReactNode
  aligne?: 'gauche' | 'droite'
  derniere?: boolean
  className?: string
}) {
  return (
    <td
      className={`py-3.5 ${derniere ? '' : 'border-b border-filet'} ${
        aligne === 'droite' ? 'chiffres text-right' : ''
      } ${className}`}
    >
      {children}
    </td>
  )
}
