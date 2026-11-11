# Auriga for Contao 5.7

Version **1.3.8**, available on `master` and `contao57`, targets **Contao 5.7.x**, **PHP 8.3+** and Symfony 7.4.
For Contao 4.13, use tag `1.2.1`.

## Installation / update

In a Contao 5.7 project:

```sh
composer require hschottm/auriga:^1.3.8 --with-all-dependencies
php vendor/bin/contao-console contao:migrate --env=prod
php vendor/bin/contao-console cache:clear --env=prod
```

Finish the upgrade to Contao 4.13 before migrating an existing Contao 4 database to Contao 5. Test the upgrade with a copy of the database and files first. Review the proposed schema changes; do not use `--with-deletes` blindly on an imported database. This package now declares the Auriga table and module-field schemas which were missing from the legacy package.

Existing module types, template names and table names are retained. Version 1.3.1 ships all eight templates as `.html.twig`, including the inactive Popwelt import template. The PHP module/content-element classes still use Contao's supported template bridge; this is not a migration to Contao 6.

Reconcile local overrides of the Auriga templates, especially `mod_auriga_song.html5` and `mod_auriga_dpi.html5`. A project-local `.html5` override takes precedence over the new Twig template: back it up outside `templates/` and port any customizations before removing it. Keep existing custom template names where module settings reference them. Twig forms use `{{ contao.request_token }}`. Each shipped template exposes a `content` block for inheritance, e.g. `{% extends "@Contao/mod_auriga_song.html.twig" %}`. Clear the production cache after updating so the template index is rebuilt. Keep the existing recording and cover files under `files/auriga/` and mark the relevant directories public in Contao's file manager.

Cover search results are cached beneath the environment's Symfony cache directory (`var/cache/<environment>/auriga`). Cover storage must be writable by the PHP process. Cover changes require membership in one of the module's configured administrator groups.

Old database and Popwelt imports remain disabled. No legacy import is run during migration.

## Validation

Tested in an isolated Contao **5.7.15** installation on **PHP 8.3.36** with MariaDB **10.11**:

- Composer installation, Contao setup, container warmup and database schema creation.
- Complete HTTP requests for all five frontend modules, the special-music content element, backend callbacks and module previews.
- Invalid/missing records, deleted target pages, search validation and recording UUIDs/legacy paths.
- Cover selection/download with mocked HTTP responses, real file storage, repair of missing covers and transaction rollback.
- Cover search cache and real Twig rendering: escaping (including stored HTML entities), wrapper attributes, cover form states and CSRF tokens, search options, provider links and the inactive backend template.
- Syntax validation of all eight Twig templates. Special-broadcast markup now closes its span even when the broadcast is not also a story.

See `tests/README.md`. The production data and customized NAS templates are not part of these fixtures.

## Template customization in 1.3.2

`ce_specialmusic.html.twig` exposes `headline`, `table_wrapper`, `table_attributes`,
`table_head`, `table_head_attributes`, `table_body` and `table_row` blocks inside
`content`. The default markup is unchanged. A project override can add Bootstrap
without duplicating the song rows:

```twig
{% extends '@Contao/ce_specialmusic.html.twig' %}

{% block table_wrapper %}
    <div class="table-responsive">{{ parent() }}</div>
{% endblock %}

{% block table_attributes %} class="table table-striped table-hover table-borderless border"{% endblock %}

{% block table_head_attributes %} class="border-top-0 border-start-0 border-end-0 border-dark border-3"{% endblock %}
```

## Broadcast template customization in 1.3.3

`mod_auriga_broadcast.html.twig` exposes `navigation_column_attributes`,
`navigation_wrapper`, `navigation_controls`, `table_wrapper`,
`titlesong_table_wrapper`, `table_attributes` and `table_head_attributes`.
The table attribute blocks apply to both broadcast and background-music tables.
The default output remains unchanged.

See `examples/templates/mod_auriga_broadcast_bootstrap.html.twig` for a Bootstrap
variant that inherits all song rows and navigation controls. Copy it to the
project's `templates/` directory and select `mod_auriga_broadcast_bootstrap` in the
Auriga broadcast module. Existing PHP overrides must be reconciled separately.

## List template customization in 1.3.4

`mod_auriga_broadcastlist.html.twig` now exposes `table_wrapper`,
`table_attributes` and `table_head_attributes`.
`mod_auriga_adventurelist.html.twig` exposes `headline`, `table_wrapper`,
`table_attributes`, `table_body`, `row_attributes`, `numbering_attributes`
and `title_attributes`. Default output is unchanged.

Bootstrap examples for both lists are provided in `examples/templates/`.
Copy the desired example into the project's `templates/` directory and select
its name in the corresponding Auriga module. The adventure list example adds
an initially expanded Bootstrap collapse panel with a generated ID, avoiding
one fixed target shared by different lists. Without a headline, the list remains
visible. Both examples inherit the data rows and preserve escaping and provider
URL validation. Clear the production cache after updating templates.

## Song cover customization in 1.3.5

`mod_auriga_song.html.twig` now exposes `cover_display` and `cover_tools` blocks.
Its default output is unchanged. The Bootstrap example in
`examples/templates/mod_auriga_song_bootstrap.html.twig` replaces only the cover
display with a non-autoplay carousel. Each slide has its own save form with the
candidate index and CSRF token; save controls are shown only to users allowed to
edit covers. Search, upload, errors and song metadata remain inherited.

Copy the example into the project's `templates/` directory and select
`mod_auriga_song_bootstrap` in the song module. Bootstrap 5's bundle must be loaded
by the page layout. Existing PHP overrides must be reconciled separately.
Carousel rendering tests cover selection indexes, CSRF tokens, guest/admin
controls, existing covers and empty/single-result states. Saving the second
candidate was also verified in the isolated integration installation with mocked
image downloads and real database/file persistence.

## Request-token correction in 1.3.6

All six form locations now obtain their CSRF token from `contao.request_token`:
the song cover selection, search and upload forms, the Bootstrap carousel save
form, the DPI search form and the inactive Popwelt import form. The PHP template
accessor `requestToken` is not automatically available as a Twig variable.

Update project-local Twig overrides and copied Bootstrap examples as well:
replace `{{ requestToken }}` with `{{ contao.request_token }}`. Package updates do
not replace these project files. Clear the production cache and open the form
page again before submitting. CSRF protection remains enabled.

Regression tests now render without injected token values and validate the
emitted tokens using Contao's real CSRF token manager.

## Improved cover lookup in 1.3.7

The existing MusicBrainz/Cover Art Archive lookup now examines up to ten releases
per query, ordered by MusicBrainz relevance, and returns at most eight distinct
front-cover URLs. If no usable front cover is found, it tries a conservatively
cleaned edition title with the artist, then the cleaned album title alone.
Fallbacks also run when releases exist but have no covers. Meaningful parenthetical
title parts are retained; remaster/deluxe/expanded/anniversary edition labels are
removed. Duplicate release IDs are queried only once per search.

Numeric 500px thumbnails are preferred, with legacy large, 1200px and original
image URLs as fallbacks. A search is bounded to three distinct MusicBrainz queries,
20 cover metadata requests and a 15-second remote-lookup budget. MusicBrainz
requests within the search instance are spaced at least 1.1 seconds apart.
Service errors do not trigger broader MusicBrainz searches. Existing positive
(24 hours) and empty-result (10 minutes) caching remains; the cache key version
was changed so old limited results are not reused. The URL-list return format
and manual cover selection/storage are unchanged.

## Cover search provider in 1.3.8

Under **System → Settings → Auriga – Cover search**, select MusicBrainz / Cover Art
Archive (the default, no credentials required) or Discogs. For Discogs, enter a
personal API token from your Discogs developer settings. Save the settings.
Existing Bootstrap overrides and the cover save form need no changes.

The token field always renders empty. Leave it empty to retain the token, enter a
new token to replace it, or select the delete checkbox to remove it. Deletion wins
if both are submitted. The token is stored in the installation's
`system/config/localconfig.php`, outside the public document root; keep this local
configuration and its backups out of source control. It is not encrypted at rest.
The secret is not included in Contao's configuration change log or frontend HTML.
Only Discogs API requests receive the authorization header; image downloads do not.

Discogs searches releases by album and artist, then tries a cleaned edition title,
then the album alone if no covers were found. At most three API calls and eight
unique cover images are returned, within a 15-second budget. Missing tokens,
authorization errors and rate limits leave the page usable with no suggestions;
they do not silently switch providers. Results are cached separately per provider
and token (24 hours for matches, 10 minutes for empty results). Existing saved
covers are unchanged. Search uses the Discogs API's `cover_image` URLs.
