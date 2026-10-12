# Regression Tests

Tests in this directory are dependency-free PHP CLI checks. They must not connect to or modify a live database.

Run the OneID SSO flow regression suite from the project root:

```bash
php tests/oneid-sso-flow-regression.php
```

Run the Additional Database compatibility suite from the project root:

```bash
php tests/additional-database-regression.php
```

The command exits with status `0` when every check passes and `1` when one or more checks fail.

Run an offline credential v2 smoke test with a process-only staging key:

```bash
DB_ADDITIONAL_CREDENTIAL_KEY="$(php -r 'echo base64_encode(random_bytes(32));')" \
DB_ADDITIONAL_CREDENTIAL_KEY_ID=staging \
php tools/additional-database-credential-smoke.php
```

The smoke command does not load the application, connect to a database, or print the key/plaintext/ciphertext.

After the offline smoke passes, run the development-registry integration smoke test:

```bash
DB_ADDITIONAL_CREDENTIAL_KEY="$(php -r 'echo base64_encode(random_bytes(32));')" \
DB_ADDITIONAL_CREDENTIAL_KEY_ID=staging \
php tools/additional-database-registry-v2-smoke.php
```

The integration command refuses non-development runtime, writes a unique fixture inside a transaction, and always rolls it back.

Review the controlled migration interface before using it:

```bash
php tools/additional-database-credential-migrate.php --help
```

The migration tool defaults to dry-run, requires an explicit `--code` or `--all` scope, and requires confirmation, backup, and connection testing for apply mode.
