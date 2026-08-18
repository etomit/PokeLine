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

1. Créer un projet Railway et y ajouter un service PostgreSQL.
2. Déployer ce dépôt dans un service applicatif.
3. Ajouter les variables `APP_KEY` (obtenue par `php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `DB_CONNECTION=pgsql`, `DB_URL=${{Postgres.DATABASE_URL}}`, `LOG_CHANNEL=stderr` et `LOG_STDERR_FORMATTER=\Monolog\Formatter\JsonFormatter`. Le service applicatif exécute automatiquement `php artisan migrate --force` avant chaque déploiement.
4. Créer un second service Railway depuis le même dépôt, définir son chemin de configuration sur `/railway.reverb.json`, puis lui générer un domaine public.
5. Définir sur les deux services les mêmes valeurs aléatoires pour `REVERB_APP_ID`, `REVERB_APP_KEY` et `REVERB_APP_SECRET`.
6. Définir `BROADCAST_CONNECTION=reverb`, `REVERB_HOST=<domaine public du service Reverb>`, `REVERB_PORT=443`, `REVERB_SCHEME=https` et `REVERB_ALLOWED_ORIGINS=<APP_URL>` sur les deux services.
7. Redéployer d’abord Reverb, puis l’application, et vérifier `/up` ainsi que l’indicateur « Temps réel connecté » dans un combat.

Les actions et les spectateurs sont synchronisés par WebSocket. Un heartbeat de présence, distinct du chargement de l’état du combat, certifie côté serveur qu’un joueur est toujours connecté et attribue la victoire après 90 secondes d’absence adverse.

## Commandes utiles

```bash
php artisan test
npm run build
php artisan migrate:fresh --seed
```

Les visuels `public/images/hub-map.png` et `public/images/trainer-sprites.png` sont des créations originales générées pour le projet ; ils ne reprennent aucun logo ni sprite Pokémon officiel.
