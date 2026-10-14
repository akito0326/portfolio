# Local PostgreSQL migration and seed CLI

The TypeScript CLI runs only from a local Node.js process. It does not load `.env`,
and it refuses to run when the `VERCEL` environment variable is present. Set the
connection string only in the terminal session that runs the command; do not
save it in project files, shell profiles, or source control. The CLI never prints
the connection string.

The CLI prefers `POSTGRES_URL_NON_POOLING` for direct database connections. If it
is not set, it falls back to `POSTGRES_URL` and prints a warning because that URL
may use a connection pooler.

From the repository root:

```sh
npm run db:migrate:status
npm run db:migrate
npm run db:seed -- --confirm
```

Run these in order: check migration status, apply pending migrations, then seed
only after reviewing the target database and data.

Migration status is read-only. Migrations use the existing SQL files in
`backend/database/migrations/` and the shared `schema_migrations` table; only
unapplied files are run, under a PostgreSQL transaction and advisory lock.

Seeding requires the explicit `--confirm` option because project and skill rows
are upserted from `src/data/*.json` and can overwrite existing content. Existing
build-log rows are left unchanged. Rows missing from the JSON files are not
deleted. The CLI does not run automatically during a Vercel deployment.

Review the target database and seed data before running either write command.
