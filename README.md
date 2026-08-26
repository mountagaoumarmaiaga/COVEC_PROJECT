# Suivi du carburant — COVEC

Application de suivi du carburant d'une station de dépôt : combien de litres
sortent de la cuve, pour quel véhicule, et ce qu'il reste en stock.

Développée d'après le cahier des charges *Application de suivi du carburant —
COVEC, version simplifiée du 25 août 2026*.

---

## Ce qui tourne aujourd'hui

| Livrable | État |
| --- | --- |
| API Laravel + base de données | Complet, 64 tests automatisés |
| Interface web React | Complet — les 3 écrans + 4 écrans de référentiel + connexion |
| Application mobile Android hors ligne | Non démarré (voir *Suite*) |

## Déploiement

L'application se déploie d'un seul tenant sur **Laravel Cloud** : Laravel sert
lui-même l'interface React, compilée dans `backend/public/app`.

Ce n'est pas un détail d'organisation. La session voyage dans un cookie en
`SameSite=Lax`, qui ne franchit pas la frontière d'un domaine : séparer
l'interface et l'API sur deux hébergeurs — Vercel d'un côté, Laravel Cloud de
l'autre — casserait l'authentification, à moins de les placer sur deux
sous-domaines d'un même domaine, ou de passer à des jetons rangés dans le
navigateur donc exposés au JavaScript. Une seule origine évite les trois
problèmes.

**Choisissez une région proche de la base.** Les 2,8 secondes mesurées depuis
un poste éloigné de Francfort sont de la distance, pas du code : un serveur
voisin de Neon les ramène sous les 100 ms.

Étapes de construction, dans cet ordre :

```bash
composer install --no-dev --optimize-autoloader
npm --prefix ../web ci && npm --prefix ../web run build
php artisan migrate --force
```

Puis les réglages de production listés en tête de
[`.env.example`](backend/.env.example), dont
`SANCTUM_STATEFUL_DOMAINS` qui doit contenir le domaine servi.

## Démarrage en développement

Deux terminaux, l'un pour l'API, l'autre pour l'interface.

```bash
cd backend && php artisan serve
```

```bash
cd web && npm run dev
```

L'interface est sur <http://localhost:5173>, l'API sur <http://127.0.0.1:8000>.
Le serveur de développement Vite relaie `/api` et `/sanctum` vers Laravel : le
navigateur ne fait aucune requête cross-origin, exactement comme en production
où Laravel sert les deux depuis la même origine.

Après un `npm run build`, l'application complète est aussi accessible sur
<http://127.0.0.1:8000> — c'est la configuration de production, utile pour la
vérifier avant de déployer.

### Première installation

```bash
cd backend && composer install
```

```bash
cd web && npm install
```

Puis choisissez l'amorçage selon l'usage.

**Pour démarrer en réel** — pose les deux carburants et leurs prix, rien
d'autre. Les compteurs partent de zéro.

```bash
php artisan migrate:fresh --seed --seeder="Database\Seeders\ReferentielSeeder"
```

Les capacités de cuve, le parc et les chauffeurs se saisissent ensuite depuis
le référentiel : ils sont propres à la station, et les inventer reviendrait à
livrer des données fausses qu'il faudrait retrouver et corriger. L'application
guide cette mise en route — l'écran de stock indique quoi renseigner, et la
saisie des sorties reste bloquée tant qu'aucun véhicule n'est déclaré.

**Pour une démonstration** — deux carburants et leurs cuves (gasoil 20 000 L,
essence 5 000 L), 8 véhicules et engins, 6 chauffeurs, 5 livraisons et
72 pleins sur deux mois, dont deux volontairement excessifs pour montrer à
quoi ressemble un plein signalé en rouge.

```bash
php artisan migrate:fresh --seed
```

## Les trois écrans du cahier des charges

| Écran | Route | Contenu |
| --- | --- | --- |
| Stock et consommation | `/` | Niveau de chaque cuve, consommation par véhicule, totaux du mois, export Excel |
| Entrées | `/entrees` | Remplissage d'une cuve : carburant, fournisseur, quantité, prix du litre |
| Sorties | `/sorties` | Un véhicule se sert : véhicule, chauffeur, litres, index compteur |

Le référentiel du §3 — véhicules, chauffeurs, carburants et cuves — occupe
trois écrans supplémentaires, saisis une seule fois.

Chaque liste se modifie et se supprime depuis sa propre ligne. Modifier un
plein relance le recalcul de toute la chaîne du véhicule, et les trois
contrôles du §5 s'appliquent à la modification comme à la création : un index
qu'on tenterait de faire passer sous le plein précédent — ou au-dessus du
suivant — est refusé de la même façon.

## Les trois contrôles obligatoires (§5)

Sans eux l'application ne ferait que recopier le cahier papier. Ils sont
implémentés et couverts par des tests dédiés
([`ControlesSaisieSortieTest`](backend/tests/Feature/ControlesSaisieSortieTest.php)).

| Situation | Comportement | Où |
| --- | --- | --- |
| Index compteur inférieur au précédent | Saisie refusée (422) | [`IndexCompteurCoherent`](backend/app/Rules/IndexCompteurCoherent.php) |
| Litres servis supérieurs à la capacité du réservoir | Saisie refusée (422) | [`LitresDansCapaciteReservoir`](backend/app/Rules/LitresDansCapaciteReservoir.php) |
| Consommation supérieure de 30 % à la moyenne | Plein signalé en rouge | [`ConsommationService`](backend/app/Services/ConsommationService.php) |

Deux précisions sur l'interprétation retenue :

- **Le troisième contrôle ne bloque pas.** Le cahier des charges demande un
  signalement, pas un refus : les litres sont réellement sortis de la cuve,
  l'historique doit les porter même quand ils sont suspects. Le plein est
  enregistré, marqué en rouge dans la liste, dans l'export Excel, et compté
  dans le bandeau « pleins signalés ».
- **Le premier contrôle s'applique dans les deux sens.** En création, seul le
  plein précédent existe. En modification, remonter un index au-dessus de celui
  du plein suivant ferait reculer le compteur vu depuis ce dernier : c'est le
  même défaut, il est refusé de la même façon.

## Carburants, prix et horodatage

La station distribue deux carburants, **gasoil à 945 FCFA** et **essence à
875 FCFA** le litre. Cela a une conséquence structurelle : le stock est une
grandeur *par carburant*.

- **Une cuve par carburant.** On ne stocke pas du gasoil et de l'essence dans
  le même réservoir, et un stock qui additionnerait les deux ne
  correspondrait à rien de physique. Chaque cuve a sa capacité, son stock et
  son prix moyen d'achat. Sur l'écran de stock, seuls les *montants* se
  totalisent toutes cuves confondues — jamais les litres.
- **Chaque véhicule est rattaché à son carburant**, ce qui décide de la cuve
  où ses pleins sont décomptés et du prix qui leur est appliqué.
- **Le prix d'un plein n'est jamais saisi.** Il vient du carburant du
  véhicule, et se fige à l'enregistrement : une hausse du tarif le mois
  suivant ne réécrit pas l'historique. Le pompiste choisit un véhicule, tape
  les litres, et voit le montant s'afficher — quatre champs, pas six.
- **Le prix d'une livraison, lui, reste saisissable.** Il est pré-rempli au
  tarif en vigueur, mais c'est le bon du fournisseur qui fait foi, et un
  fournisseur ne facture pas forcément au prix de la station.
- **La date et l'heure sont posées par le serveur** à la création d'un
  mouvement, jamais par le poste de saisie : deux postes peuvent avoir des
  horloges déréglées différemment, et l'ordre de la chaîne de consommation en
  dépend. L'horodatage redevient corrigeable en modification, pour rattraper
  un plein enregistré en retard.

## Comment la consommation est calculée

Méthode du plein complet : les litres servis lors d'un plein remplacent ce qui
a été consommé depuis le plein précédent.

```
distance      = index(n) − index(n−1)
L/100 km      = litres(n) ÷ distance × 100     (mode « km »)
L/h           = litres(n) ÷ distance           (mode « heures »)
```

Trois conséquences à connaître :

- **Le premier plein d'un véhicule n'a pas de consommation.** Il n'existe aucun
  index de départ ; il sert de repère aux suivants.
- **Le deuxième a une consommation mais aucune moyenne de référence.** Il ne
  peut donc pas être signalé en rouge : il n'y a rien à quoi le comparer.
- **La moyenne est pondérée**, pas une moyenne de moyennes : litres cumulés
  rapportés à la distance cumulée. Un plein pris après 20 km ne doit pas peser
  autant qu'un plein pris après 800 km.

La chaîne s'ordonne sur l'horodatage à la seconde ; l'identifiant ne départage
que deux pleins enregistrés au même instant.

Toute écriture — création, modification, suppression — relance le recalcul de
la chaîne complète du véhicule. Une saisie antidatée s'insère au milieu de
l'historique et décale la distance de tous les pleins suivants ; ne recalculer
que le dernier laisserait des consommations fausses derrière soi.

## Modèle de données

Quatre tables métier, comme le prévoit le §7 :

```
vehicules    code interne, désignation, carburant, mode_suivi (km|heures),
             capacite_reservoir
chauffeurs   nom, matricule
entrees      horodatage, carburant, fournisseur, quantite_litres, prix_unitaire
sorties      horodatage, vehicule, chauffeur, litres_servis, prix_unitaire,
             index_compteur
             + colonnes calculées : distance, consommation, moyenne, écart, anomalie
```

Deux tables de référentiel s'y ajoutent : `carburants` (code, libellé, prix du
litre en vigueur) et `cuves`, une par carburant. Le §3 les range dans le
référentiel « saisi une seule fois » ; elles valent des lignes en base plutôt
que des constantes dans le code, pour rester modifiables depuis l'interface
sans redéploiement.

Le stock n'est jamais stocké dans une colonne : il est recalculé comme la
différence entre les entrées et les sorties. Un compteur entretenu à la main
finirait par diverger de l'historique des mouvements, et c'est l'historique qui
fait foi.

## Export Excel

`GET /api/exports/mensuel?annee=2026&mois=8` produit un classeur à quatre
onglets : Synthèse, Entrées, Sorties, Consommation par véhicule. Les pleins
signalés en rouge le restent dans le fichier.

L'export utilise **openspout** plutôt que `maatwebsite/excel` : ce dernier
dépend de PhpSpreadsheet, qui exige l'extension PHP `gd`, absente de
l'installation de cette machine. Aucune modification du `php.ini` n'a été faite.

## Authentification et rôles

Connexion par **matricule** et mot de passe. Pas d'adresse électronique : le
matricule est déjà la référence de l'entreprise, il se tape vite sur un poste
de station, et il n'oblige pas à en donner une à chaque pompiste. La
contrepartie est qu'il n'y a pas de réinitialisation par courriel — c'est le
gestionnaire qui réattribue un mot de passe depuis l'écran **Comptes**.

Trois rôles, et ce que chacun peut faire :

| | Pompiste | Gestionnaire | Consultation |
| --- | :---: | :---: | :---: |
| Consulter le stock et la consommation | ✓ | ✓ | ✓ |
| Export Excel | ✓ | ✓ | ✓ |
| Enregistrer un plein | ✓ | ✓ | |
| Corriger ou supprimer un plein | | ✓ | |
| Enregistrer une livraison | | ✓ | |
| Tenir le référentiel et les comptes | | ✓ | |

**Un pompiste enregistre ses pleins mais ne les corrige pas.** Ce n'est pas de
la défiance : sans cette séparation, les trois contrôles du §5 ne
protégeraient plus rien, puisqu'une saisie refusée se contournerait en
retouchant le plein précédent.

La matrice se lit dans [`routes/api.php`](backend/routes/api.php) — les routes
y sont écrites une par une plutôt qu'en `apiResource`, précisément pour cela.
L'interface masque ce qu'un rôle ne peut pas faire, mais c'est le serveur qui
refuse : les droits arrivent avec le compte connecté, l'interface ne les
recalcule jamais.

La session voyage dans un cookie inaccessible au JavaScript, via Sanctum en
mode SPA. L'application mobile prendra un jeton Sanctum le moment venu — les
deux mécanismes cohabitent sur les mêmes routes.

### Premier accès

L'amorçage crée un compte **ADMIN** avec le mot de passe `covec2026`.
**Changez-le à la première connexion**, depuis *Mon compte*. Un autre mot de
passe peut être imposé dès l'installation :

```bash
COVEC_ADMIN_PASSWORD="votre-mot-de-passe" php artisan migrate:fresh --seed --seeder="Database\Seeders\ReferentielSeeder"
```

Le dernier gestionnaire actif ne peut être ni supprimé, ni rétrogradé, ni
désactivé : sans ce garde-fou, une station peut se retrouver enfermée dehors,
sans plus aucun compte capable de tenir le référentiel.

## Avant la mise en production

Un audit de sécurité a été passé sur l'application. Ce qui a été **corrigé
dans le code** :

- **Freinage des tentatives de connexion.** Il n'y en avait aucun : vingt
  essais de mot de passe passaient sans le moindre blocage, et les matricules
  d'une station sont courts et devinables. Deux limites se cumulent
  désormais — cinq essais par minute sur un compte donné, vingt par minute
  depuis une même adresse, la seconde arrêtant le balayage d'une liste de
  matricules que la première ne verrait pas passer. Le changement de mot de
  passe est freiné de la même façon.

Ce qui a été **vérifié et tenu** : toutes les routes répondent 401 sans
session, écritures et export compris ; aucune écriture n'échappe au contrôle
de rôle ; le SQL brut ne contient aucune donnée d'utilisateur ; aucune
dépendance vulnérable côté PHP ni côté JavaScript ; aucune injection HTML dans
le front ; aucun jeton en `localStorage`, la session étant dans un cookie
inaccessible au JavaScript.

Ce qui **relève du déploiement**, et reste donc à faire au moment de
l'installation — le détail est en tête de
[`.env.example`](backend/.env.example) :

| Réglage | Pourquoi |
| --- | --- |
| `APP_DEBUG=false` | En `true`, une erreur expose les chemins du serveur et la pile d'appels |
| `APP_ENV=production` | Désactive les facilités de développement |
| `SESSION_SECURE_COOKIE=true` | Sinon le cookie de session voyage aussi en clair sur HTTP |
| `SESSION_ENCRYPT=true` | Chiffre le contenu de la session stocké en base |
| `SANCTUM_STATEFUL_DOMAINS` | Le domaine de production, et lui seul |
| Mot de passe `ADMIN` | `covec2026` est un mot de passe d'installation, à changer à la première connexion |

Deux points connus, laissés tels quels et assumés :

- **Le mot de passe minimum est de huit caractères.** Suffisant avec le
  freinage en place ; à relever si l'application devient accessible depuis
  l'extérieur du réseau de la station.
- **`allowed_origins` vaut `*` dans la configuration CORS.** Sans effet ici :
  les cookies ne partent pas en cross-origin (`supports_credentials` est
  faux) et toutes les routes exigent une session, donc une origine tierce ne
  récolte que des 401. À restreindre malgré tout si l'API est un jour servie
  sur un domaine distinct de l'interface.

## Direction visuelle

L'interface suit une direction éditoriale inspirée d'[impeccable.style](https://impeccable.style/designing/),
appliquée au suivi du carburant. Les valeurs vivent dans le bloc `@theme` de
[`web/src/index.css`](web/src/index.css) ; les maquettes d'origine sont dans
[`design/`](design/).

- **Deux familles**, toutes deux sur Google Fonts : *Alumni Sans* en graisse
  100 à 300 pour les titres et les chiffres, *Albert Sans* pour l'interface.
  Les surtitres sont en capitales monospace interlettrées à 11 px.
- **Un filet plutôt qu'un cadre.** Les sections se séparent par un trait d'un
  pixel. Seuls les formulaires de saisie s'élèvent du papier, parce qu'ils
  demandent une action. Les champs n'ont pas de boîte : un filet sous la
  valeur, comme un registre tenu à la main.
- **Les listes déroulantes sont dessinées**, pas natives
  ([`Selecteur.tsx`](web/src/components/Selecteur.tsx)). Le menu d'un
  `<select>` est rendu par le système d'exploitation : ni sa police, ni son
  surlignage bleu, ni ses angles ne sont atteignables en CSS, et il jurait
  franchement avec le reste. Le composant suit le motif ARIA
  « select-only combobox » — flèches, Début/Fin, recherche par frappe, Entrée,
  Échap, `aria-activedescendant` — et se replie vers le haut ou se recale
  horizontalement quand la fenêtre manque de place.
- **Les chiffres sont l'objet principal.** Le volume restant s'affiche jusqu'à
  148 px en graisse 100 : c'est la question que le gestionnaire pose en premier
  chaque matin, elle doit se lire à distance.
- **Le vermillon est réservé au §5** — saisie refusée et plein signalé. Il ne
  sert nulle part ailleurs, y compris dans les notifications, qui sont habillées
  à la main pour cette raison. C'est ce qui lui garde son pouvoir d'arrêt.

L'interface est en thème clair uniquement. Le système source définit aussi un
thème sombre, non repris ici faute de besoin exprimé.

## Choix techniques

| Sujet | Décision |
| --- | --- |
| Base de données | PostgreSQL, hébergé sur Neon |
| Authentification | Absente — le cahier des charges n'en prévoit pas. **À ajouter avant toute mise en ligne hors du réseau de la station.** |
| Valorisation des sorties | Coût unitaire moyen pondéré de tous les achats. Un litre servi n'a pas de prix propre. |
| Suppression d'un véhicule ou d'un chauffeur | Refusée (409) dès qu'un historique existe. On désactive, on n'efface pas des litres réellement sortis. |
| Monnaie | Franc CFA, affiché sans centimes |

### La base est sur Neon

Deux points appris à l'installation, et qui coûteraient une soirée à
retrouver :

**Utilisez le point d'accès direct, pas le pooler.** L'URL fournie par Neon
contient `-pooler` ; retirez-le. Le pooler travaille en mode transaction et
les migrations, qui envoient leur DDL en requêtes préparées à l'intérieur
d'une transaction, y échouent sur un `current transaction is aborted` qui
masque la vraie erreur. Vérifié : la même migration passe sur le point direct
et casse sur le pooler.

**Session et cache restent en fichiers locaux.** Les ranger dans la base
ajoutait trois allers-retours vers Francfort à chaque requête HTTP, pour des
données qui n'ont aucune raison d'être distantes tant que l'application
tourne sur un seul serveur.

`DB_PERSISTENT=true` réutilise la connexion TLS d'une requête à l'autre —
sans quoi chaque requête repaie environ 600 ms d'ouverture.

### Ce que coûte une base distante

Depuis un poste éloigné de Francfort, une requête SQL coûte environ **270 ms
d'aller-retour**, et ouvrir une connexion environ **600 ms**. Un écran qui
enchaîne dix requêtes met donc près de trois secondes, quelle que soit la
qualité du code.

Les totaux sont pour cette raison calculés par agrégats groupés plutôt que
carburant par carburant. Le gain mesuré sur l'écran de stock : **10,2 s à
2,8 s**. Le reste est de la distance, pas du code — pour descendre plus bas,
il faut héberger l'application dans la même région que la base.

## Tests

```bash
cd backend && php artisan test
```

64 tests, 207 assertions. Sept fichiers :
`ControlesSaisieSortieTest` (les trois contrôles du §5),
`StockEtConsommationTest` (stock par carburant, unités, recalcul de chaîne),
`HorodatageEtPrixTest` (horodatage serveur, prix repris du carburant),
`ExportMensuelTest` (contenu du classeur),
`AuthentificationTest` (connexion par matricule, comptes désactivés, mots de passe),
`RolesTest` (la matrice des permissions, et le garde-fou du dernier gestionnaire),
`LimitationTentativesTest` (freinage des tentatives de connexion).

## Hors périmètre

Conformément au §6, ne sont pas couverts : la ventilation par chantier, la
validation hiérarchique des pleins, la photo du compteur, la géolocalisation,
le jaugeage physique de la cuve, les rapports de direction et la maintenance
des véhicules.

## Le point à trancher

> *La station est-elle équipée d'une pompe à compteur totalisateur ?*

**Question toujours ouverte.** La colonne `index_pompe` existe déjà sur la table
`sorties`, nullable, et le champ correspondant est présent dans le formulaire de
saisie, marqué facultatif. Rien n'est imposé tant que l'équipement n'est pas
confirmé.

Le jour où la réponse est oui, le rapprochement entre l'index de la pompe et la
somme des litres servis devient le contrôle le plus fiable du dispositif — il
vérifie la cuve elle-même, là où les trois contrôles actuels ne vérifient que la
cohérence des saisies entre elles. L'ajouter ne demandera pas de migration
lourde : le champ est déjà là.

## Suite

1. **Application mobile Android** avec saisie hors ligne à la station (§7). Le
   modèle de données et l'API sont figés, c'est le bon moment pour l'attaquer.
   La file d'attente hors ligne devra rejouer les sorties dans l'ordre
   chronologique, sans quoi le contrôle d'index les refusera à la
   resynchronisation.
2. **Authentification**, indispensable avant toute exposition hors du réseau
   local.
3. **Contrôles complémentaires** non demandés par le cahier des charges mais
   naturels : refuser une livraison qui ferait déborder la cuve, refuser une
   sortie supérieure au stock disponible.
