import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react'

/*
  Liste déroulante dessinée, en remplacement du <select> natif.

  Le menu déroulant d'un <select> est rendu par le système d'exploitation :
  ni sa police, ni son surlignage bleu, ni ses angles ne sont atteignables en
  CSS. Sur un écran de station où tout le reste est en papier chaud et
  capitales interlettrées, le menu natif jurait franchement.

  Le composant suit le motif ARIA « select-only combobox » : le focus ne
  quitte jamais le déclencheur, et l'option survolée au clavier est désignée
  par aria-activedescendant. C'est ce que les lecteurs d'écran attendent, et
  cela évite de jongler avec le focus à l'ouverture et à la fermeture.
*/

export interface OptionListe {
  valeur: string
  libelle: string
  /** Seconde ligne — la désignation qui suit le code d'un véhicule, par exemple. */
  detail?: string
}

interface Props {
  options: OptionListe[]
  value: string
  onChange: (valeur: string) => void
  placeholder?: string
  /** Variante des barres de filtres : plus petite, filet plus discret. */
  compact?: boolean
  invalide?: boolean
  disabled?: boolean
  id?: string
  'aria-label'?: string
  className?: string
}

/** Délai au-delà duquel une nouvelle frappe recommence la recherche. */
const REPRISE_FRAPPE = 700

export function Selecteur({
  options,
  value,
  onChange,
  placeholder = 'Choisir…',
  compact = false,
  invalide = false,
  disabled = false,
  id,
  className = '',
  ...reste
}: Props) {
  const [ouvert, setOuvert] = useState(false)
  const [actif, setActif] = useState(-1)
  const [versLeHaut, setVersLeHaut] = useState(false)

  const conteneur = useRef<HTMLDivElement>(null)
  const declencheur = useRef<HTMLButtonElement>(null)
  const liste = useRef<HTMLUListElement>(null)
  const frappe = useRef({ texte: '', quand: 0 })

  const idBase = useId()
  const idListe = `${idBase}-liste`
  const idOption = (i: number) => `${idBase}-option-${i}`

  const indexChoisi = options.findIndex((o) => o.valeur === value)
  const choisie = indexChoisi >= 0 ? options[indexChoisi] : null

  const ouvrir = (depart = indexChoisi >= 0 ? indexChoisi : 0) => {
    if (disabled) return
    setActif(options.length ? depart : -1)
    setOuvert(true)
  }

  const fermer = () => {
    setOuvert(false)
    setActif(-1)
  }

  const valider = (i: number) => {
    const option = options[i]
    if (!option) return
    onChange(option.valeur)
    fermer()
    declencheur.current?.focus()
  }

  // Ouvre vers le haut quand le bas de la fenêtre ne laisse pas la place :
  // à la station, la saisie se fait souvent sur un écran court.
  useLayoutEffect(() => {
    if (!ouvert || !declencheur.current) return

    const rect = declencheur.current.getBoundingClientRect()
    const souhaitee = Math.min(options.length * 44 + 12, 280)
    const dessous = window.innerHeight - rect.bottom
    setVersLeHaut(dessous < souhaitee && rect.top > dessous)
  }, [ouvert, options.length])

  /*
    Rattrape la liste quand elle sort de la fenêtre.

    La variante compacte s'aligne sur le bord droit de son déclencheur et
    s'étend vers la gauche, à la largeur de son contenu. Un filtre étroit
    placé près du bord gauche — « Filtrer par véhicule » et ses désignations
    de 200 px — passait donc hors de l'écran.
  */
  useLayoutEffect(() => {
    const el = liste.current
    if (!ouvert || !el) return

    el.style.transform = ''

    const marge = 12
    const rect = el.getBoundingClientRect()

    const decalage =
      rect.left < marge
        ? marge - rect.left
        : rect.right > window.innerWidth - marge
          ? window.innerWidth - marge - rect.right
          : 0

    if (decalage !== 0) el.style.transform = `translateX(${Math.round(decalage)}px)`
  }, [ouvert, versLeHaut, options.length])

  // Garde l'option survolée dans le champ de vision pendant la navigation.
  useEffect(() => {
    if (!ouvert || actif < 0) return
    liste.current
      ?.querySelector(`#${CSS.escape(idOption(actif))}`)
      ?.scrollIntoView({ block: 'nearest' })
  }, [ouvert, actif])

  // Un clic hors du composant referme, sans avaler le clic lui-même.
  useEffect(() => {
    if (!ouvert) return

    const surClic = (e: PointerEvent) => {
      if (!conteneur.current?.contains(e.target as Node)) fermer()
    }

    document.addEventListener('pointerdown', surClic)

    return () => document.removeEventListener('pointerdown', surClic)
  }, [ouvert])

  /** Saisie au clavier : saute à la première option qui commence par la frappe. */
  const chercher = (touche: string) => {
    const maintenant = Date.now()
    const suite =
      maintenant - frappe.current.quand > REPRISE_FRAPPE
        ? touche
        : frappe.current.texte + touche

    frappe.current = { texte: suite, quand: maintenant }

    const cible = options.findIndex((o) =>
      o.libelle.toLowerCase().startsWith(suite.toLowerCase()),
    )

    if (cible >= 0) {
      if (ouvert) setActif(cible)
      else onChange(options[cible].valeur)
    }
  }

  const surTouche = (e: React.KeyboardEvent) => {
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault()
        if (!ouvert) ouvrir()
        else setActif((i) => Math.min(i + 1, options.length - 1))
        break

      case 'ArrowUp':
        e.preventDefault()
        if (!ouvert) ouvrir(indexChoisi >= 0 ? indexChoisi : options.length - 1)
        else setActif((i) => Math.max(i - 1, 0))
        break

      case 'Home':
        if (ouvert) {
          e.preventDefault()
          setActif(0)
        }
        break

      case 'End':
        if (ouvert) {
          e.preventDefault()
          setActif(options.length - 1)
        }
        break

      case 'Enter':
      case ' ':
        e.preventDefault()
        if (ouvert) valider(actif)
        else ouvrir()
        break

      case 'Escape':
        if (ouvert) {
          e.preventDefault()
          fermer()
        }
        break

      case 'Tab':
        // Tab quitte le champ : on referme sans rien choisir.
        if (ouvert) fermer()
        break

      default:
        if (e.key.length === 1 && !e.metaKey && !e.ctrlKey && !e.altKey) {
          chercher(e.key)
        }
    }
  }

  const tailles = compact
    ? 'py-1 text-[13px]'
    : 'py-[7px] pb-[9px] text-base'

  return (
    <div ref={conteneur} className={`relative ${compact ? 'w-auto' : 'w-full'} ${className}`}>
      <button
        {...reste}
        ref={declencheur}
        type="button"
        id={id}
        role="combobox"
        aria-expanded={ouvert}
        aria-controls={idListe}
        aria-haspopup="listbox"
        aria-activedescendant={ouvert && actif >= 0 ? idOption(actif) : undefined}
        aria-invalid={invalide || undefined}
        disabled={disabled}
        onClick={() => (ouvert ? fermer() : ouvrir())}
        onKeyDown={surTouche}
        className={`champ champ-liste ${tailles} ${
          compact ? 'border-b-filet' : ''
        } ${choisie ? '' : 'text-pale'}`}
      >
        <span className="truncate">{choisie ? choisie.libelle : placeholder}</span>
        <svg
          width="11"
          height="7"
          viewBox="0 0 11 7"
          fill="none"
          aria-hidden="true"
          className={`shrink-0 transition-transform duration-150 ${ouvert ? 'rotate-180' : ''}`}
        >
          <path
            d="M1 1.5 5.5 5.5 10 1.5"
            stroke="currentColor"
            strokeWidth="1.2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="text-attenue"
          />
        </svg>
      </button>

      {ouvert && (
        <ul
          ref={liste}
          id={idListe}
          role="listbox"
          tabIndex={-1}
          className={`absolute z-30 max-h-70 min-w-full max-w-[min(22rem,calc(100vw-1.5rem))] overflow-y-auto rounded-net border border-arete bg-leve py-1 shadow-flottant ${
            versLeHaut ? 'bottom-full mb-1.5' : 'top-full mt-1.5'
          } ${compact ? 'right-0 w-max' : 'left-0 right-0'}`}
        >
          {options.length === 0 && (
            <li className="px-3.5 py-2.5 text-[13px] text-pale">Aucune option</li>
          )}

          {options.map((option, i) => {
            const estChoisie = option.valeur === value
            const estActive = i === actif

            return (
              <li
                key={option.valeur}
                id={idOption(i)}
                role="option"
                aria-selected={estChoisie}
                // pointerdown plutôt que click : le clic hors zone se déclenche
                // au pointerdown, il fermerait la liste avant le choix.
                onPointerDown={(e) => {
                  e.preventDefault()
                  valider(i)
                }}
                onPointerEnter={() => setActif(i)}
                className={`cursor-pointer border-l-2 px-3.5 py-2.5 transition-colors ${
                  estActive ? 'bg-papier-profond' : 'bg-transparent'
                } ${estChoisie ? 'border-l-kinpaku' : 'border-l-transparent'}`}
              >
                <span
                  className={`block truncate text-sm ${
                    estChoisie ? 'font-medium text-encre' : 'text-encre'
                  }`}
                >
                  {option.libelle}
                </span>
                {option.detail && (
                  <span className="mt-0.5 block truncate text-xs text-pale">
                    {option.detail}
                  </span>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
