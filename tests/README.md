# Contao 5.7 tests

The Contao 4.13 stub tests were replaced with an integration harness using real Contao models, services, templates and a database on this branch.

## Offline tests

```sh
export AURIGA_VENDOR_AUTOLOAD=/absolute/path/to/contao57/vendor/autoload.php
php tests/cover-search.php
```

HTTP responses are mocked; these tests do not use a database.

## Integration tests

Prepare a disposable Contao 5.7 installation with this package installed and a dedicated database whose name starts with `auriga57_test_`. Configure a non-empty Symfony `framework.secret` in the test installation. Run Contao setup and migrations using that same test `DATABASE_URL` before the scripts.

```sh
export AURIGA_TEST_PROJECT=/absolute/path/to/disposable-contao57
export DATABASE_URL='mysql://USER:PASSWORD@HOST/auriga57_test_local?serverVersion=mariadb-10.11.19'
php tests/contao57-integration.php
php tests/template-output.php
php tests/song-carousel.php
php tests/contao57-http.php
```

**The integration harness deletes and reseeds fixture tables in that database. Never point it at an existing website.** It writes cover fixtures inside the test project's `files/` directory. The HTTP test must run after the integration test; it requests the prepared fixture page through Contao's full HttpKernel.

The template-output test uses the real Contao Twig/legacy bridge (no PHP-template stubs) to cover escaping, HTML attributes, form tokens and cover states.

The scripts test rendering, backend callbacks, missing records, search input validation, UUID/path handling, real cover persistence and rollback. External image requests are mocked. The manual browser file-upload transport and production data are not covered.

The carousel test checks per-slide selection indexes and CSRF tokens, selected-slide state, guest/admin controls, inherited search/upload forms and empty/single-result states.

## Recording assignment migration

`php tests/recording-migration.php` exercises the standalone planner without a database.
`tests/recording-migration-integration.php` uses the real DBAL/Symfony dependencies
from `AURIGA_VENDOR_AUTOLOAD` and a disposable MariaDB database named by
`AURIGA_RECORDING_TEST_DB` (must start with `auriga57_test_recordings_`). It uses
`AURIGA_TEST_DB_HOST` (default `mariadb`) and the local fixture account `contao`.
It drops and recreates four fixture tables; never use a production database.
Coverage includes dry-run, multiple UUID selections, report/backup permissions,
idempotence, missing references, transactional rollback and command registration.

`tests/recording-registration-contao.php` additionally runs the real Contao Dbafs
API in a disposable installed project (`AURIGA_TEST_PROJECT`, `DATABASE_URL` with
an `auriga57_test_` database). It checks physical-but-unregistered recordings,
ancestor registration, UUID selection, idempotence, unrelated files staying
unregistered, and Dbafs participation in the same rollback transaction.
