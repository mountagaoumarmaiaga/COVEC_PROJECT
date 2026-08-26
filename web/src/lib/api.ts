import axios, { AxiosError } from 'axios'

export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
  // La session voyage dans un cookie, inaccessible au JavaScript. Axios doit
  // donc l'envoyer explicitement, et renvoyer le jeton anti-CSRF que Laravel
  // dépose à côté.
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

/**
 * Récupère le cookie anti-CSRF avant une écriture non authentifiée.
 *
 * Laravel exige un jeton sur toute requête d'écriture. Le navigateur ne l'a
 * pas encore au premier chargement : il faut le demander avant de tenter une
 * connexion.
 */
export async function preparerCsrf(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

interface ReponseValidation {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Messages d'erreur d'une requête refusée, prêts à être affichés.
 *
 * Les refus du §5 du cahier des charges arrivent en 422 avec le détail par
 * champ. Ce sont eux qui doivent s'afficher au pompiste — pas un « erreur
 * 422 » qui ne lui dirait pas pourquoi son plein a été rejeté.
 */
export function messagesErreur(erreur: unknown): string[] {
  if (erreur instanceof AxiosError) {
    const reponse = erreur.response?.data as ReponseValidation | undefined

    if (reponse?.errors) {
      return Object.values(reponse.errors).flat()
    }

    if (reponse?.message) {
      return [reponse.message]
    }

    if (erreur.response?.status === 403) {
      return ['Votre rôle ne permet pas cette action.']
    }

    if (erreur.response?.status === 419) {
      return ['Votre session a expiré. Rechargez la page et reconnectez-vous.']
    }

    if (erreur.code === 'ERR_NETWORK') {
      return ['Serveur injoignable. Vérifiez que l’API Laravel est démarrée.']
    }
  }

  return ['Une erreur inattendue est survenue.']
}

/** Erreurs rangées par champ, pour les afficher sous la bonne case du formulaire. */
export function erreursParChamp(erreur: unknown): Record<string, string[]> {
  if (erreur instanceof AxiosError) {
    return (erreur.response?.data as ReponseValidation | undefined)?.errors ?? {}
  }

  return {}
}

/** Vrai quand le serveur signale une session absente ou expirée. */
export function sessionPerdue(erreur: unknown): boolean {
  return erreur instanceof AxiosError && [401, 419].includes(erreur.response?.status ?? 0)
}
