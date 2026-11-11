# Auriga for Contao 5.7

Version **1.3.1**, available on `master` and `contao57`, targets **Contao 5.7.x**, **PHP 8.3+** and Symfony 7.4.
For Contao 4.13, use tag `1.2.1`.

## Installation / update

In a Contao 5.7 project:

```sh
composer require hschottm/auriga:^1.3.1 --with-all-dependencies
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
