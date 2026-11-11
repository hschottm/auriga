# Auriga for Contao 5.7

Version **1.3.5**, available on `master` and `contao57`, targets **Contao 5.7.x**, **PHP 8.3+** and Symfony 7.4.
For Contao 4.13, use tag `1.2.1`.

## Installation / update

In a Contao 5.7 project:

```sh
composer require hschottm/auriga:^1.3.5 --with-all-dependencies
php vendor/bin/contao-console contao:migrate --env=prod
php vendor/bin/contao-console cache:clear --env=prod
```

Finish the upgrade to Contao 4.13 before migrating an existing Contao 4 database to Contao 5. Test the upgrade with a copy of the database and files first. Review the proposed schema changes; do not use `--with-deletes` blindly on an imported database. This package now declares the Auriga table and module-field schemas which were missing from the legacy package.

Existing module types, template names and table names are retained. Version 1.3.1 ships all eight templates as `.html.twig`, including the inactive Popwelt import template. The PHP module/content-element classes still use Contao's supported template bridge; this is not a migration to Contao 6.

Reconcile local overrides of the Auriga templates, especially `mod_auriga_song.html5` and `mod_auriga_dpi.html5`. A project-local `.html5` override takes precedence over the new Twig template: back it up outside `templates/` and port any customizations before removing it. Keep existing custom template names where module settings reference them. Twig forms use `{{ requestToken }}`. Each shipped template exposes a `content` block for inheritance, e.g. `{% extends "@Contao/mod_auriga_song.html.twig" %}`. Clear the production cache after updating so the template index is rebuilt. Keep the existing recording and cover files under `files/auriga/` and mark the relevant directories public in Contao's file manager.

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
