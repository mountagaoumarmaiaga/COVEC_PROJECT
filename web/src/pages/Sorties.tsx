import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Clock, Pencil, Plus, Trash2, X } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'

import {
  AlerteErreurs,
  Badge,
  Bouton,
  Carte,
  Cellule,
  Champ,
  Chargement,
  Chiffre,
  Guidage,
  EnteteColonne,
  EnteteEcran,
  EtatVide,
  LienAction,
  Liste,
  ListeFiltre,
  Saisie,
  Surtitre,
  Tableau,
} from '@/components/ui'
import { api, erreursParChamp, messagesErreur } from '@/lib/api'
import { useDroits } from '@/lib/auth'
import {
  depuisChampHorodatage,
  formaterConsommation,
  formaterDate,
  formaterEcart,
  formaterEntier,
  formaterHeure,
  formaterLitres,
  formaterMontant,
  formaterNombre,
  MOIS,
  versChampHorodatage,
} from '@/lib/format'
import type { Chauffeur, Page, Sortie, Vehicule } from '@/types'

interface Formulaire {
  vehicule_id: string
  chauffeur_id: string
  litres_servis: string
  index_compteur: string
  index_pompe: string
  /** Vide en création : le serveur pose l'instant. Rempli en correction. */
  date_sortie: string
}

const FORMULAIRE_VIDE: Formulaire = {
  vehicule_id: '',
  chauffeur_id: '',
  litres_servis: '',
  index_compteur: '',
  index_pompe: '',
  date_sortie: '',
}

export function Sorties() {
  const queryClient = useQueryClient()
  const { peutServir, peutGerer } = useDroits()
  const [formulaire, setFormulaire] = useState<Formulaire>(FORMULAIRE_VIDE)
  const [enEdition, setEnEdition] = useState<Sortie | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({})
  const [messageGlobal, setMessageGlobal] = useState<string[]>([])
  const formulaireRef = useRef<HTMLFormElement>(null)

  const maintenant = new Date()
  const [filtreVehicule, setFiltreVehicule] = useState('')
  const [filtreMois, setFiltreMois] = useState(String(maintenant.getMonth() + 1))
  const [filtreAnnee, setFiltreAnnee] = useState(String(maintenant.getFullYear()))
  const [anomaliesSeulement, setAnomaliesSeulement] = useState(false)

  const { data: vehicules } = useQuery({
    queryKey: ['vehicules', 'actifs'],
    queryFn: async () =>
      (await api.get<{ data: Vehicule[] }>('/vehicules', { params: { actifs_seulement: 1 } }))
        .data.data,
  })

  const { data: chauffeurs } = useQuery({
    queryKey: ['chauffeurs', 'actifs'],
    queryFn: async () =>
      (await api.get<{ data: Chauffeur[] }>('/chauffeurs', { params: { actifs_seulement: 1 } }))
        .data.data,
  })

  const manqueVehicules = vehicules !== undefined && vehicules.length === 0
  const manqueChauffeurs = chauffeurs !== undefined && chauffeurs.length === 0
  const referentielIncomplet = manqueVehicules || manqueChauffeurs

  const vehiculeChoisi = useMemo(
    () => vehicules?.find((v) => String(v.id) === formulaire.vehicule_id),
    [vehicules, formulaire.vehicule_id],
  )

  /*
    Prix appliqué au plein. Il n'est jamais saisi : il vient du carburant du
    véhicule. Un plein déjà enregistré garde le sien — c'est un fait daté — et
    ne repasse au tarif courant que s'il change de véhicule, donc peut-être de
    carburant. Le serveur applique exactement la même règle.
  */
  const prixApplique = useMemo(() => {
    const memeVehicule = String(enEdition?.vehicule?.id ?? '') === formulaire.vehicule_id

    return enEdition && memeVehicule
      ? enEdition.prix_unitaire
      : (vehiculeChoisi?.carburant?.prix_par_defaut ?? 0)
  }, [enEdition, vehiculeChoisi, formulaire.vehicule_id])

  const montantEstime = (Number(formulaire.litres_servis) || 0) * prixApplique

  // Historique du véhicule sélectionné : sert à situer l'index saisi entre
  // celui du plein précédent et celui du plein suivant.
  const { data: historiqueVehicule } = useQuery({
    queryKey: ['historique-vehicule', formulaire.vehicule_id],
    enabled: Boolean(formulaire.vehicule_id),
    queryFn: async () =>
      (
        await api.get<Page<Sortie>>(`/vehicules/${formulaire.vehicule_id}/historique`, {
          params: { par_page: 200 },
        })
      ).data.data,
  })

  /*
    Les deux pleins qui encadrent celui en cours de saisie.

    Le tri reproduit celui du serveur — horodatage puis identifiant. Une saisie
    non encore enregistrée se place en dernier, exactement comme le fait
    ConsommationService.
  */
  const bornes = useMemo(() => {
    const rang = (instant: string, id: number) => `${instant}#${String(id).padStart(12, '0')}`

    const chaine = (historiqueVehicule ?? [])
      .filter((s) => s.id !== enEdition?.id)
      .map((s) => ({ sortie: s, rang: rang(s.date_sortie, s.id) }))
      .sort((a, b) => a.rang.localeCompare(b.rang))

    const instantSaisi = formulaire.date_sortie
      ? depuisChampHorodatage(formulaire.date_sortie)
      : '9999-12-31 23:59:59'

    const moi = rang(instantSaisi, enEdition?.id ?? Number.MAX_SAFE_INTEGER)

    return {
      avant: [...chaine].reverse().find((e) => e.rang < moi)?.sortie ?? null,
      apres: chaine.find((e) => e.rang > moi)?.sortie ?? null,
    }
  }, [historiqueVehicule, enEdition, formulaire.date_sortie])

  /**
   * Repère affiché sous le champ d'index compteur.
   *
   * Tant qu'aucun plein antérieur n'existe, le message dit ce qu'est l'index
   * plutôt que de laisser deviner : « index compteur » est du vocabulaire de
   * parc, pas du langage courant, et on peut croire qu'il s'agit de la
   * distance parcourue depuis le dernier plein.
   */
  const indicationIndex = () => {
    if (!vehiculeChoisi) {
      return 'Le total affiché au compteur du véhicule, relevé au moment du plein.'
    }

    const unite = vehiculeChoisi.unite_index
    const dit = (s: Sortie) =>
      `${formaterNombre(s.index_compteur)} ${unite} le ${formaterDate(s.date_sortie)} à ${formaterHeure(s.date_sortie)}`

    if (bornes.avant && bornes.apres) return `Entre ${dit(bornes.avant)} et ${dit(bornes.apres)}`
    if (bornes.avant) return `Dernier relevé : ${dit(bornes.avant)}`
    if (bornes.apres) return `Ne doit pas dépasser ${dit(bornes.apres)}`

    return vehiculeChoisi.mode_suivi === 'km'
      ? 'Premier plein : relevez le kilométrage total au tableau de bord. Il servira de repère aux suivants.'
      : 'Premier plein : relevez le total d’heures au compteur horaire. Il servira de repère aux suivants.'
  }

  const { data: sorties, isLoading } = useQuery({
    queryKey: ['sorties', filtreVehicule, filtreAnnee, filtreMois, anomaliesSeulement],
    queryFn: async () =>
      (
        await api.get<Page<Sortie>>('/sorties', {
          params: {
            vehicule_id: filtreVehicule || undefined,
            annee: filtreMois ? filtreAnnee : undefined,
            mois: filtreMois || undefined,
            anomalies_seulement: anomaliesSeulement ? 1 : undefined,
            par_page: 50,
          },
        })
      ).data,
  })

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['sorties'] })
    queryClient.invalidateQueries({ queryKey: ['stock'] })
    queryClient.invalidateQueries({ queryKey: ['historique-vehicule'] })
  }

  const reinitialiser = () => {
    setEnEdition(null)
    setErreurs({})
    setMessageGlobal([])
    setFormulaire(FORMULAIRE_VIDE)
  }

  const editer = (sortie: Sortie) => {
    setEnEdition(sortie)
    setErreurs({})
    setMessageGlobal([])
    setFormulaire({
      vehicule_id: String(sortie.vehicule?.id ?? ''),
      chauffeur_id: String(sortie.chauffeur?.id ?? ''),
      litres_servis: String(sortie.litres_servis),
      index_compteur: String(sortie.index_compteur),
      index_pompe: sortie.index_pompe === null ? '' : String(sortie.index_pompe),
      date_sortie: versChampHorodatage(sortie.date_sortie),
    })

    formulaireRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }

  const enregistrer = useMutation({
    mutationFn: async (valeurs: Formulaire) => {
      const corps = {
        // Ni l'heure ni le prix ne partent d'ici en création : le serveur les
        // pose. En correction seul l'horodatage est renvoyé.
        ...(enEdition && valeurs.date_sortie
          ? { date_sortie: depuisChampHorodatage(valeurs.date_sortie) }
          : {}),
        vehicule_id: valeurs.vehicule_id ? Number(valeurs.vehicule_id) : null,
        chauffeur_id: valeurs.chauffeur_id ? Number(valeurs.chauffeur_id) : null,
        litres_servis: Number(valeurs.litres_servis),
        index_compteur: Number(valeurs.index_compteur),
        index_pompe: valeurs.index_pompe === '' ? null : Number(valeurs.index_pompe),
      }

      const reponse = enEdition
        ? await api.put<{ data: Sortie }>(`/sorties/${enEdition.id}`, corps)
        : await api.post<{ data: Sortie }>('/sorties', corps)

      return reponse.data.data
    },
    onSuccess: (sortie) => {
      const modifiait = Boolean(enEdition)

      setErreurs({})
      setMessageGlobal([])
      setEnEdition(null)

      if (sortie.anomalie) {
        toast.warning(modifiait ? 'Plein modifié et signalé' : 'Plein enregistré et signalé', {
          description: `Consommation ${formaterConsommation(
            sortie.consommation,
            sortie.vehicule?.unite_consommation ?? '',
          )}, soit ${formaterEcart(sortie.ecart_pourcentage)} par rapport à la moyenne du véhicule.`,
        })
      } else {
        toast.success(modifiait ? 'Plein modifié' : 'Plein enregistré', {
          description: `${formaterLitres(sortie.litres_servis)} · ${formaterMontant(sortie.montant)} · ${formaterHeure(sortie.date_sortie)}`,
        })
      }

      // En création, le véhicule reste en place : à la station, les pleins
      // s'enchaînent souvent sur le même engin.
      setFormulaire((precedent) =>
        modifiait
          ? FORMULAIRE_VIDE
          : {
              ...precedent,
              chauffeur_id: '',
              litres_servis: '',
              index_compteur: '',
              index_pompe: '',
              date_sortie: '',
            },
      )

      rafraichir()
    },
    onError: (erreur) => {
      setErreurs(erreursParChamp(erreur))
      setMessageGlobal(messagesErreur(erreur))
      toast.error('Saisie refusée')
    },
  })

  const supprimer = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/sorties/${id}`)

      return id
    },
    onSuccess: (id) => {
      toast.success('Plein supprimé, consommations recalculées')

      if (enEdition?.id === id) reinitialiser()

      rafraichir()
    },
    onError: (erreur) => toast.error(messagesErreur(erreur)[0]),
  })

  const modifier = (champ: keyof Formulaire, valeur: string) =>
    setFormulaire((precedent) => ({ ...precedent, [champ]: valeur }))

  return (
    <div className="space-y-12">
      <EnteteEcran surtitre="Écran 2 · un véhicule se sert à la cuve" titre="Sorties">
        Le véhicule, le chauffeur, les litres et l’index. Le carburant, son prix et l’heure exacte
        sont posés tout seuls : quatre champs à remplir, pas six.
      </EnteteEcran>

      {peutServir && referentielIncomplet && (
        <Guidage
          titre="Le référentiel n’est pas encore complet"
          action={
            <div className="flex flex-wrap gap-4 text-[13px]">
              <Link to="/vehicules" className="text-patine-profonde hover:text-patine">
                Ajouter un véhicule
              </Link>
              <Link to="/chauffeurs" className="text-patine-profonde hover:text-patine">
                Ajouter un chauffeur
              </Link>
            </div>
          }
        >
          {manqueVehicules && manqueChauffeurs
            ? 'Aucun véhicule ni chauffeur n’est enregistré : un plein a besoin des deux.'
            : manqueVehicules
              ? 'Aucun véhicule actif n’est enregistré : commencez par déclarer le parc et son carburant.'
              : 'Aucun chauffeur actif n’est enregistré : un plein est toujours servi à quelqu’un.'}
        </Guidage>
      )}

      {peutServir && (
      <Carte
        titre={
          enEdition
            ? `Modifier le plein du ${formaterDate(enEdition.date_sortie)} à ${formaterHeure(enEdition.date_sortie)}`
            : 'Nouveau plein'
        }
        levee
        actions={
          enEdition && (
            <Bouton variante="secondaire" type="button" onClick={reinitialiser}>
              <X className="size-4" aria-hidden />
              Annuler
            </Bouton>
          )
        }
      >
        <form
          ref={formulaireRef}
          className="space-y-7"
          onSubmit={(e) => {
            e.preventDefault()
            enregistrer.mutate(formulaire)
          }}
        >
          <AlerteErreurs messages={messageGlobal} />

          <div className="grid gap-x-9 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
            <Champ label="Véhicule" obligatoire erreurs={erreurs.vehicule_id}>
              <Liste
                value={formulaire.vehicule_id}
                onChange={(valeur) => modifier('vehicule_id', valeur)}
                invalide={Boolean(erreurs.vehicule_id?.length)}
                // Carburant et capacité accompagnent chaque véhicule : le
                // premier décide du prix, la seconde du contrôle n°2.
                options={(vehicules ?? []).map((vehicule) => ({
                  valeur: String(vehicule.id),
                  libelle: `${vehicule.code} — ${vehicule.designation}`,
                  detail: `${vehicule.carburant?.libelle ?? 'Carburant non défini'} · réservoir ${formaterLitres(vehicule.capacite_reservoir)}`,
                }))}
              />
            </Champ>

            <Champ label="Chauffeur" obligatoire erreurs={erreurs.chauffeur_id}>
              <Liste
                value={formulaire.chauffeur_id}
                onChange={(valeur) => modifier('chauffeur_id', valeur)}
                invalide={Boolean(erreurs.chauffeur_id?.length)}
                options={(chauffeurs ?? []).map((chauffeur) => ({
                  valeur: String(chauffeur.id),
                  libelle: chauffeur.nom,
                  detail: chauffeur.matricule,
                }))}
              />
            </Champ>

            <Champ
              label="Litres servis"
              obligatoire
              erreurs={erreurs.litres_servis}
              indication={
                vehiculeChoisi
                  ? `Réservoir de ${formaterLitres(vehiculeChoisi.capacite_reservoir)} au maximum`
                  : undefined
              }
            >
              <Saisie
                type="number"
                step="0.01"
                min="0.01"
                required
                aria-invalid={Boolean(erreurs.litres_servis?.length)}
                value={formulaire.litres_servis}
                onChange={(e) => modifier('litres_servis', e.target.value)}
              />
            </Champ>

            <Champ
              label={`Index compteur${vehiculeChoisi ? ` (${vehiculeChoisi.unite_index})` : ''}`}
              obligatoire
              erreurs={erreurs.index_compteur}
              indication={indicationIndex()}
            >
              <Saisie
                type="number"
                step="0.01"
                min="0"
                required
                aria-invalid={Boolean(erreurs.index_compteur?.length)}
                value={formulaire.index_compteur}
                onChange={(e) => modifier('index_compteur', e.target.value)}
              />
            </Champ>

            <Champ
              label="Index pompe"
              erreurs={erreurs.index_pompe}
              indication="Facultatif, si la pompe a un compteur totalisateur."
            >
              <Saisie
                type="number"
                step="0.01"
                min="0"
                value={formulaire.index_pompe}
                onChange={(e) => modifier('index_pompe', e.target.value)}
              />
            </Champ>

            {/*
              Le montant n'est pas un champ : c'est le résultat du carburant du
              véhicule et des litres servis. Il s'affiche pour que le pompiste
              puisse l'annoncer au chauffeur sans calcul.
            */}
            <div className="flex flex-col justify-end">
              <Surtitre className="mb-0.5">
                {vehiculeChoisi?.carburant
                  ? `${vehiculeChoisi.carburant.libelle} · ${formaterMontant(prixApplique)} / L`
                  : 'Montant'}
              </Surtitre>
              <div className="pt-0.5 pb-2">
                <Chiffre
                  valeur={formaterEntier(montantEstime)}
                  unite="FCFA"
                  taille="grand"
                  ton="or"
                />
              </div>
            </div>
          </div>

          {enEdition ? (
            <div className="max-w-sm">
              <Champ
                label="Date et heure"
                erreurs={erreurs.date_sortie}
                indication="Corrigez uniquement si le plein a été enregistré en retard."
              >
                <Saisie
                  type="datetime-local"
                  value={formulaire.date_sortie}
                  onChange={(e) => modifier('date_sortie', e.target.value)}
                />
              </Champ>
            </div>
          ) : (
            <p className="flex items-center gap-2 text-xs text-pale">
              <Clock className="size-3.5" aria-hidden />
              La date et l’heure exactes sont enregistrées automatiquement à la validation.
            </p>
          )}

          <div className="flex justify-end">
            <Bouton type="submit" disabled={enregistrer.isPending || referentielIncomplet}>
              {enEdition ? (
                <Pencil className="size-4" aria-hidden />
              ) : (
                <Plus className="size-4" aria-hidden />
              )}
              {enregistrer.isPending
                ? 'Enregistrement…'
                : enEdition
                  ? 'Enregistrer les modifications'
                  : 'Enregistrer le plein'}
            </Bouton>
          </div>
        </form>
      </Carte>
      )}

      <Carte
        titre="Historique des pleins"
        description="Un plein dont la consommation dépasse de plus de 30 % la moyenne du véhicule est signalé, jamais refusé : les litres sont réellement sortis de la cuve."
        actions={
          <>
            <ListeFiltre
              aria-label="Filtrer par véhicule"
              value={filtreVehicule}
              onChange={setFiltreVehicule}
              options={[
                { valeur: '', libelle: 'Tous les véhicules' },
                ...(vehicules ?? []).map((vehicule) => ({
                  valeur: String(vehicule.id),
                  libelle: vehicule.code,
                  detail: vehicule.designation,
                })),
              ]}
            />
            <ListeFiltre
              aria-label="Filtrer par mois"
              value={filtreMois}
              onChange={setFiltreMois}
              options={[
                { valeur: '', libelle: 'Tous les mois' },
                ...MOIS.map((libelle, index) => ({ valeur: String(index + 1), libelle })),
              ]}
            />
            <ListeFiltre
              aria-label="Filtrer par année"
              value={filtreAnnee}
              onChange={setFiltreAnnee}
              options={Array.from({ length: 6 }, (_, i) => maintenant.getFullYear() - i).map(
                (a) => ({ valeur: String(a), libelle: String(a) }),
              )}
            />
            <LienAction
              actif={anomaliesSeulement}
              onClick={() => setAnomaliesSeulement((v) => !v)}
              className={anomaliesSeulement ? '' : 'text-vermillon!'}
            >
              Signalés seulement
            </LienAction>
          </>
        }
      >
        {isLoading ? (
          <Chargement />
        ) : !sorties || sorties.data.length === 0 ? (
          <EtatVide message="Aucun plein pour ces critères." />
        ) : (
          <Tableau>
            <thead>
              <tr>
                <EnteteColonne>Date et heure</EnteteColonne>
                <EnteteColonne>Véhicule</EnteteColonne>
                <EnteteColonne>Chauffeur</EnteteColonne>
                <EnteteColonne aligne="droite">Litres</EnteteColonne>
                <EnteteColonne aligne="droite">Montant</EnteteColonne>
                <EnteteColonne aligne="droite">Index</EnteteColonne>
                <EnteteColonne aligne="droite">Consommation</EnteteColonne>
                <EnteteColonne aligne="droite">Écart</EnteteColonne>
                <EnteteColonne aligne="droite">
                  <span className="sr-only">Actions</span>
                </EnteteColonne>
              </tr>
            </thead>
            <tbody>
              {sorties.data.map((sortie, index) => {
                const derniere = index === sorties.data.length - 1

                return (
                  <tr
                    key={sortie.id}
                    className={
                      // La ligne en cours de modification prime sur le
                      // signalement : il faut voir ce qu'on est en train de
                      // toucher, même si ce plein est par ailleurs en rouge.
                      enEdition?.id === sortie.id
                        ? 'bg-kinpaku-pale/30'
                        : sortie.anomalie
                          ? 'bg-vermillon-voile'
                          : 'transition-colors hover:bg-papier-profond'
                    }
                  >
                    <Cellule
                      derniere={derniere}
                      className={
                        sortie.anomalie ? 'border-l-2 border-l-vermillon pl-3' : undefined
                      }
                    >
                      <span className="chiffres text-attenue">
                        {formaterDate(sortie.date_sortie)}
                      </span>
                      <span className="chiffres block text-xs text-pale">
                        {formaterHeure(sortie.date_sortie)}
                      </span>
                    </Cellule>
                    <Cellule derniere={derniere}>
                      <span className="font-mono text-xs tracking-[0.06em]">
                        {sortie.vehicule?.code}
                      </span>
                      <span className="block text-xs text-pale">
                        {sortie.vehicule?.carburant?.libelle}
                      </span>
                    </Cellule>
                    <Cellule derniere={derniere}>{sortie.chauffeur?.nom}</Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      {formaterLitres(sortie.litres_servis)}
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      {formaterMontant(sortie.montant)}
                      <span className="block text-xs text-pale">
                        {formaterMontant(sortie.prix_unitaire)} / L
                      </span>
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite" className="text-attenue">
                      {formaterNombre(sortie.index_compteur)} {sortie.vehicule?.unite_index}
                      {sortie.distance_parcourue !== null && (
                        <span className="block text-xs text-pale">
                          +{formaterNombre(sortie.distance_parcourue)}{' '}
                          {sortie.vehicule?.unite_index}
                        </span>
                      )}
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      {sortie.consommation === null ? (
                        <span className="text-pale">—</span>
                      ) : (
                        <span
                          className={`font-display text-[20px] ${
                            sortie.anomalie ? 'font-medium text-vermillon' : 'font-normal'
                          }`}
                        >
                          {formaterNombre(sortie.consommation)}
                          <span className="ml-1 font-sans text-xs font-normal text-attenue">
                            {sortie.vehicule?.unite_consommation}
                          </span>
                          {sortie.anomalie && sortie.moyenne_reference !== null && (
                            <span className="block font-sans text-[11px] font-normal text-pale">
                              moyenne {formaterNombre(sortie.moyenne_reference)}
                            </span>
                          )}
                        </span>
                      )}
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      {sortie.anomalie ? (
                        <Badge ton="signale">{formaterEcart(sortie.ecart_pourcentage)}</Badge>
                      ) : (
                        <span className="text-attenue">
                          {formaterEcart(sortie.ecart_pourcentage)}
                        </span>
                      )}
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      <div className="flex justify-end gap-1">
                        {/* Un pompiste enregistre ses pleins mais ne les
                            corrige pas : l'API le refuse, l'interface ne le
                            propose donc pas. */}
                        {peutGerer && (
                        <Bouton
                          variante="discret"
                          type="button"
                          aria-label={`Modifier le plein du ${formaterDate(sortie.date_sortie)}`}
                          onClick={() => editer(sortie)}
                        >
                          <Pencil className="size-4" aria-hidden />
                        </Bouton>
                        )}
                        {peutGerer && (
                        <Bouton
                          variante="discret"
                          type="button"
                          aria-label={`Supprimer le plein du ${formaterDate(sortie.date_sortie)}`}
                          onClick={() => {
                            if (
                              window.confirm(
                                'Supprimer ce plein ? Les consommations du véhicule seront recalculées.',
                              )
                            ) {
                              supprimer.mutate(sortie.id)
                            }
                          }}
                        >
                          <Trash2 className="size-4" aria-hidden />
                        </Bouton>
                        )}
                      </div>
                    </Cellule>
                  </tr>
                )
              })}
            </tbody>
          </Tableau>
        )}
      </Carte>
    </div>
  )
}
