# SEO avec AML

AML centralise les réglages SEO du projet, génère les fichiers destinés aux
moteurs de recherche et audite le HTML réellement publié.

## Initialiser et modifier la configuration

```bash
aml seo:init
aml seo:set title "My website"
aml seo:set description "A clear description between 50 and 160 characters."
aml seo:set base_url "https://example.com"
aml seo:set image "/img/og.png"
aml seo:show
aml seo:show --json
```

Les clés simples disponibles sont `site_name`, `title`, `description`, `base_url`,
`locale`, `robots`, `image`, `twitter_card`, `type`, `author` et
`theme_color`. Le fichier reste modifiable manuellement dans
`configs/seo.json`.

## Autoriser ou interdire l’exploration

```bash
aml seo:disallow /admin
aml seo:disallow /account
aml seo:allow /admin/public
aml seo:remove disallow /account
aml seo:show
```

Les règles sont enregistrées dans les tableaux `allow` et `disallow`. Lors de
la génération, elles sont écrites dans `robots.txt` et les routes interdites
sont retirées du sitemap. Une interdiction s’applique aussi aux sous-chemins :
`/admin` exclut donc `/admin/users`. Une règle `allow` plus précise, comme
`/admin/public`, peut rendre ce sous-chemin indexable à nouveau.

`Disallow` est une instruction destinée aux robots, pas une protection de
sécurité. Utilisez toujours l’authentification et les middlewares PHPAML pour
empêcher réellement l’accès à une page privée.

Le modèle officiel utilise cette configuration pour produire le titre, la
description, l’URL canonique, Open Graph, Twitter Cards, la couleur du thème et
les données structurées Schema.org au format JSON-LD.

## Générer robots.txt et sitemap.xml

```bash
aml seo:generate
```

AML crée `public/robots.txt` et `public/sitemap.xml`. Le sitemap contient les
routes statiques `GET`; les routes dynamiques avec paramètres sont ignorées,
car AML ne peut pas deviner leurs valeurs.

Relancez cette commande après avoir ajouté une route publique ou changé
`base_url`.

## Auditer le site

```bash
aml seo:audit
aml seo:audit https://example.com
aml seo:audit https://example.com --json
aml seo:audit https://example.com --file public/page.html --json
```

La forme courte `aml seo` lance aussi l’audit. AML vérifie le titre, la longueur
de la description, l’URL canonique, Open Graph, Twitter Card, l’unique `h1`, les
attributs `alt` des images, la langue du document, le viewport mobile, les
données structurées JSON-LD et HTTPS. La commande retourne un code d’erreur si
une vérification échoue, ce qui permet de l’utiliser dans une intégration continue.

L’option `--file` analyse un fichier HTML local sans démarrer de serveur. L’URL
reste utilisée pour vérifier HTTPS et doit représenter l’adresse publique finale.

L’audit aide à détecter les erreurs techniques; il ne garantit pas le
classement dans les moteurs de recherche ni la qualité éditoriale du contenu.
