# Branch maintenance

| Branch | PHP requirement | Runtime profile |
| --- | --- | --- |
| `php5` | `>=5.4 <7.0` | `php54` |
| `php7` | `>=7.0 <8.0` | `php70`, `php72` |
| `php8` | `>=8.0` | `php80` |

Apply protocol fixes in `php5` first, then transfer the reviewed change to
`php7` and `php8`. Newer branches may use their supported language features
only when the public contract and RouterOS wire bytes remain equivalent.

Install the required test profile before testing:

```sh
docker compose run --rm composer --working-dir=/app/tools/phpunit/php54 install
docker compose run --rm php54 php tools/test.php
docker compose run --rm composer --working-dir=/app/tools install
docker compose run --rm php54 php tools/vendor/bin/phpcs --standard=phpcs.xml.dist
docker compose run --rm php54 php tools/vendor/bin/phpcs --runtime-set installed_paths tools/vendor/phpcompatibility/php-compatibility --standard=phpcompatibility.xml.dist
docker compose run --rm php80 php tools/vendor/bin/phpstan analyse src --configuration=phpstan.neon --no-progress
```

The PHP 5.4 Docker run is mandatory for `php5`. PHP 7.2, PHP 7.0, and PHP 8.0
are additional runtime profiles, not replacements for that minimum-version
check. Composer runs in its own current, TLS-enabled container; the target PHP
containers execute only the installed tests. This keeps dependency installation
independent of obsolete crypto libraries in PHP 5.4.
