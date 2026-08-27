import { LogOut } from 'lucide-react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'

import { useAuth } from '@/lib/auth'

/*
  La navigation quitte la barre latérale sombre pour un bandeau de tête.

  Un suivi de carburant se lit comme un registre : le contenu doit occuper
  toute la largeur du papier, pas les deux tiers restés libres à côté d'un
  panneau. Le référentiel, saisi une seule fois, descend d'un cran dans la
  hiérarchie — et n'apparaît qu'aux comptes qui peuvent le tenir.
*/

const ECRANS = [
  { to: '/', libelle: 'Stock', exact: true },
  { to: '/sorties', libelle: 'Sorties' },
  { to: '/entrees', libelle: 'Entrées' },
]

const REFERENTIEL = [
  { to: '/vehicules', libelle: 'Véhicules et engins' },
  { to: '/chauffeurs', libelle: 'Chauffeurs' },
  { to: '/carburants', libelle: 'Carburants et cuves' },
  { to: '/utilisateurs', libelle: 'Comptes' },
]

const CHEMINS_REFERENTIEL = REFERENTIEL.map((e) => e.to)

/*
  Le pseudo-élément agrandit la zone cliquable à environ 45 px sans déplacer
  le soulignement : à la station on tape avec le pouce, souvent avec des gants,
  et un lien de 25 px de haut se rate. La ligne dorée, elle, doit rester collée
  au texte.
*/
const ZONE_TACTILE =
  "relative after:absolute after:inset-x-0 after:-inset-y-2.5 after:content-['']"

function lienClasse({ isActive }: { isActive: boolean }) {
  return `${ZONE_TACTILE} pb-1 text-[13px] transition-colors ${
    isActive
      ? 'border-b border-kinpaku text-encre'
      : 'border-b border-transparent text-patine-profonde hover:text-patine'
  }`
}

export function Layout() {
  const { pathname } = useLocation()
  const navigate = useNavigate()
  const { utilisateur, deconnecter } = useAuth()

  // Le référentiel n'est ouvert qu'aux comptes qui peuvent le tenir : l'y
  // laisser visible en lecture seule promettrait une action impossible.
  const tientLeReferentiel = utilisateur?.peut_gerer ?? false
  const dansReferentiel = tientLeReferentiel && CHEMINS_REFERENTIEL.includes(pathname)

  const seDeconnecter = async () => {
    await deconnecter()
    navigate('/', { replace: true })
  }

  return (
    <div className="min-h-screen bg-papier">
      <header className="border-b border-filet">
        <div className="mx-auto flex max-w-[1280px] flex-wrap items-baseline justify-between gap-x-8 gap-y-2.5 px-5 pt-5 pb-3 sm:gap-y-3 sm:px-6 sm:pt-7 sm:pb-4 lg:px-16">
          <div className="flex items-baseline gap-3.5">
            <span className="font-display text-[22px] font-medium tracking-[0.15em] uppercase">
              COVEC
            </span>
            <span className="hidden h-3.5 w-px self-center bg-arete sm:inline-block" aria-hidden />
            <span className="hidden text-[11px] tracking-[0.18em] text-attenue uppercase sm:inline">
              Suivi du carburant
            </span>
          </div>

          <div className="flex flex-wrap items-baseline gap-x-5 gap-y-2.5 sm:gap-x-7 sm:gap-y-3">
            <nav className="flex flex-wrap items-baseline gap-x-5 gap-y-2 sm:gap-x-7">
              {ECRANS.map((ecran) => (
                <NavLink key={ecran.to} to={ecran.to} end={ecran.exact} className={lienClasse}>
                  {ecran.libelle}
                </NavLink>
              ))}
              {tientLeReferentiel && (
                <NavLink
                  to="/vehicules"
                  className={() => lienClasse({ isActive: dansReferentiel })}
                >
                  Référentiel
                </NavLink>
              )}
            </nav>

            {utilisateur && (
              <div className="flex items-baseline gap-3 border-filet sm:gap-3.5 sm:border-l sm:pl-7">
                <NavLink to="/mon-compte" className={lienClasse}>
                  <span className="font-medium">{utilisateur.nom}</span>
                  <span className="ml-2 hidden text-[11px] tracking-[0.14em] text-pale uppercase sm:inline">
                    {utilisateur.role_libelle}
                  </span>
                </NavLink>
                <button
                  type="button"
                  onClick={seDeconnecter}
                  aria-label="Se déconnecter"
                  // Carré cerné plutôt qu'icône nue : une icône de seize
                  // pixels posée sur le papier ne se lit pas comme un bouton,
                  // et sa zone tactile restait aussi étroite qu'elle.
                  className="relative inline-flex size-9 shrink-0 cursor-pointer items-center justify-center self-center rounded-net border border-filet bg-papier-profond text-attenue transition-colors hover:border-arete hover:text-encre focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-arete after:absolute after:-inset-1 after:content-['']"

                >
                  <LogOut className="size-4" aria-hidden />
                </button>
              </div>
            )}
          </div>
        </div>

        {dansReferentiel && (
          <div className="border-t border-filet bg-papier-profond">
            <nav className="mx-auto flex max-w-[1280px] flex-wrap items-baseline gap-x-5 gap-y-2 px-5 py-2 sm:gap-x-7 sm:px-6 sm:py-2.5 lg:px-16">
              {REFERENTIEL.map((ecran) => (
                <NavLink key={ecran.to} to={ecran.to} className={lienClasse}>
                  {ecran.libelle}
                </NavLink>
              ))}
            </nav>
          </div>
        )}
      </header>

      <main className="mx-auto max-w-[1280px] px-5 py-8 sm:px-6 sm:py-11 lg:px-16">
        <Outlet />
      </main>
    </div>
  )
}
