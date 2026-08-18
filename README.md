# PokéLine

Jeu de combat Pokémon multijoueur réalisé avec Laravel 12, PokeAPI et une architecture MVC. Tous les Pokémon sont normalisés au niveau 100. Le projet propose un mode solo contre une IA tactique, un mode local à deux joueurs et des salons en ligne persistés en base.

## Fonctionnalités

- hub 2D jouable au clavier ou à la souris, avec sprite animé et trois destinations ;
- interface rétro inspirée des consoles portables, responsive ;
- français/anglais avec détection de la langue navigateur, cookie invité et préférence de compte ;
- authentification Laravel sans compte obligatoire pour le solo et le local ;
- données Pokémon et attaques récupérées depuis PokeAPI puis mises en cache ;
- formule de statistiques niveau 100 (IV 31, EV 0, nature neutre), STAB, précision, priorité, vitesse et table complète des types ;
- IA à score inspirée des décisions offensives de la génération Noir/Blanc : immunités, STAB, efficacité, précision, dégâts estimés et priorité au K.O. ;
- neuf objets tenus avec effets de combat ; inventaire exclusivement gagné en ligne ;
- récompenses atomiques : 1–3 objets au vainqueur et 0–2 au perdant ;
- jusqu’à 10 équipes persistées, de 1 à 6 Pokémon chacune ;
- combat en ligne simultané et anti-double-action, verrouillé en transaction SQL ;
- synchronisation WebSocket avec Laravel Reverb, présence en direct et victoire après 90 secondes de déconnexion adverse ;
- animation d’attaque/d’impact et effets audio 8-bit désactivables.

## Architecture

- `app/Models` : User, Team, TeamPokemon, Item, InventoryItem, Battle.
- `app/Http/Controllers` : authentification, équipes, proxy PokeAPI et combats.
- `app/Services/PokeApiService.php` : cache et normalisation PokeAPI.
- `app/Services/BattleEngine.php` : moteur déterminant les tours côté serveur.
- `app/Services/TypeChart.php` : multiplicateurs des 18 types.
- `resources/views` : vues Blade MVC ; `resources/js/app.js` gère uniquement l’affichage et les commandes.

## Installation locale

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
php artisan reverb:start
```

Par défaut, SQLite est utilisé. Sous XAMPP, activez `extension=zip` pour Composer et `extension=pdo_pgsql` si vous utilisez PostgreSQL.

## Déploiement Railway

Railway doit contenir trois services nommés exactement `PokeLine`, `Reverb` et `Postgres`. Le nom exact est important, car il est utilisé dans les références `${{Service.VARIABLE}}`.

### 1. Créer le service Reverb

1. Depuis le projet Railway, choisir **New > GitHub Repo** et sélectionner le même dépôt que `PokeLine`.
2. Nommer ce nouveau service `Reverb`.
3. Dans **Settings > Config as Code > Config File Path**, saisir `/railway.reverb.json`.
4. Dans **Settings > Networking**, cliquer sur **Generate Domain**. Il n'est pas nécessaire de recopier ce domaine : Railway l'expose automatiquement dans `Reverb.RAILWAY_PUBLIC_DOMAIN`.

### 2. Créer les trois identifiants Reverb partagés

Ces identifiants ne sont pas fournis par un service externe : c'est nous qui les créons une seule fois.

- `REVERB_APP_ID` identifie l'application dans Reverb. La valeur fixe `pokeline-production` convient.
- `REVERB_APP_KEY` est l'identifiant public transmis au navigateur.
- `REVERB_APP_SECRET` signe les communications serveur et doit rester secret.

Dans un terminal local, générer la clé et le secret :

```bash
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Dans **Project Settings > Shared Variables**, créer :

```env
REVERB_APP_ID=pokeline-production
REVERB_APP_KEY=COLLER_LE_RESULTAT_DE_LA_PREMIERE_COMMANDE
REVERB_APP_SECRET=COLLER_LE_RESULTAT_DE_LA_DEUXIEME_COMMANDE
```

Utiliser ensuite le bouton **Share** pour partager ces trois variables avec `PokeLine` et `Reverb`. Il ne faut pas générer deux jeux de valeurs : les deux services doivent recevoir exactement les mêmes identifiants.

### 3. Variables du service PokeLine

Dans **PokeLine > Variables**, ajouter ou vérifier :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
DB_CONNECTION=pgsql
DB_URL=${{Postgres.DATABASE_URL}}
LOG_CHANNEL=stderr
BROADCAST_CONNECTION=reverb
REVERB_HOST=${{Reverb.RAILWAY_PUBLIC_DOMAIN}}
REVERB_PORT=443
REVERB_SCHEME=https
```

Conserver la valeur `APP_KEY` déjà utilisée par l'application. Si elle n'existe pas encore, exécuter localement `php artisan key:generate --show` et coller le résultat. La commande de pré-déploiement de `/railway.json` exécute automatiquement les migrations.

### 4. Variables du service Reverb

Dans **Reverb > Variables**, ajouter :

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=${{PokeLine.APP_KEY}}
LOG_CHANNEL=stderr
REVERB_HOST=${{RAILWAY_PUBLIC_DOMAIN}}
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_ALLOWED_ORIGINS=${{PokeLine.RAILWAY_PUBLIC_DOMAIN}}
```

`REVERB_ALLOWED_ORIGINS` contient uniquement le domaine, sans `https://` et sans barre oblique finale.
Le service Reverb est construit avec `Dockerfile.reverb`, qui compile `pcntl`, `posix` et `sockets`, puis vérifie les constantes de signaux Unix avant de produire l'image. Il ne faut donc pas ajouter `RAILPACK_PHP_EXTENSIONS` à ce service.

### 5. Déployer et vérifier

1. Valider les changements Railway avec **Deploy**.
2. Redéployer d'abord `Reverb`, puis `PokeLine`.
3. Ouvrir un combat avec deux comptes dans deux navigateurs.
4. Vérifier que l'interface affiche **Temps réel connecté**.

Il n'y a donc aucun domaine à copier manuellement : `${{RAILWAY_PUBLIC_DOMAIN}}`, `${{Reverb.RAILWAY_PUBLIC_DOMAIN}}`, `${{PokeLine.RAILWAY_PUBLIC_DOMAIN}}` et `${{Postgres.DATABASE_URL}}` sont résolus automatiquement par Railway.

Les actions et les spectateurs sont synchronisés par WebSocket. Un heartbeat de présence, distinct du chargement de l’état du combat, certifie côté serveur qu’un joueur est toujours connecté et attribue la victoire après 90 secondes d’absence adverse.

## Commandes utiles

```bash
php artisan test
npm run build
php artisan migrate:fresh --seed
```

Les visuels `public/images/hub-map.png` et `public/images/trainer-sprites.png` sont des créations originales générées pour le projet ; ils ne reprennent aucun logo ni sprite Pokémon officiel.
