import { readFile, readdir } from "node:fs/promises";
import { basename, join } from "node:path";
import { fileURLToPath } from "node:url";
import { Pool, type PoolClient } from "pg";

type JsonRecord = Record<string, unknown>;

class CliError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "CliError";
  }
}

const migrationDirectory = fileURLToPath(
  new URL("../backend/database/migrations/", import.meta.url),
);
const seedDirectory = fileURLToPath(new URL("../src/data/", import.meta.url));

function usage(): never {
  console.error(
    "Usage: tsx scripts/database.ts migrate [--status] | seed --confirm",
  );
  process.exit(2);
}

function assertLocalExecution(): void {
  if (process.env.VERCEL) {
    throw new CliError("This database CLI is for local execution only.");
  }
}

function createPool(): Pool {
  const directConnectionString = process.env.POSTGRES_URL_NON_POOLING;
  const connectionString = directConnectionString || process.env.POSTGRES_URL;

  if (!connectionString) {
    throw new CliError(
      "POSTGRES_URL_NON_POOLING or POSTGRES_URL is required in the local shell environment. No environment file is loaded.",
    );
  }

  if (!directConnectionString) {
    console.warn(
      "POSTGRES_URL_NON_POOLING is not set; falling back to POSTGRES_URL, which may use a pooler.",
    );
  }

  return new Pool({
    connectionString,
    max: 1,
    connectionTimeoutMillis: 10_000,
    idleTimeoutMillis: 5_000,
  });
}

async function migrationFiles(): Promise<string[]> {
  return (await readdir(migrationDirectory))
    .filter((filename) => filename.endsWith(".sql"))
    .sort()
    .map((filename) => join(migrationDirectory, filename));
}

async function runMigrations(statusOnly: boolean): Promise<void> {
  const files = await migrationFiles();
  const pool = createPool();
  let client: PoolClient | undefined;

  try {
    client = await pool.connect();

    if (statusOnly) {
      const schema = await client.query<{ table_exists: boolean }>(
        "SELECT to_regclass('public.schema_migrations') IS NOT NULL AS table_exists",
      );
      const applied = schema.rows[0]?.table_exists
        ? new Set(
            (
              await client.query<{ version: string }>(
                "SELECT version FROM schema_migrations",
              )
            ).rows.map(({ version }) => version),
          )
        : new Set<string>();

      for (const file of files) {
        const version = basename(file, ".sql");
        console.log(`${applied.has(version) ? "[applied]" : "[pending]"} ${version}`);
      }
      return;
    }

    await client.query("BEGIN");
    await client.query("SELECT pg_advisory_xact_lock(1784941901, 1)");
    await client.query(`
      CREATE TABLE IF NOT EXISTS schema_migrations (
        version TEXT PRIMARY KEY,
        applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
      )
    `);

    let migratedCount = 0;

    for (const file of files) {
      const version = basename(file, ".sql");
      const existing = await client.query(
        "SELECT 1 FROM schema_migrations WHERE version = $1",
        [version],
      );

      if (existing.rowCount) {
        console.log(`[applied] ${version}`);
        continue;
      }

      const sql = await readFile(file, "utf8");
      if (sql.trim() === "") {
        throw new CliError(`Migration ${version} is empty.`);
      }

      await client.query(sql);
      await client.query("INSERT INTO schema_migrations (version) VALUES ($1)", [
        version,
      ]);
      console.log(`[migrated] ${version}`);
      migratedCount += 1;
    }

    await client.query("COMMIT");
    console.log(
      migratedCount === 0
        ? "No pending migrations."
        : `Applied ${migratedCount} migration(s).`,
    );
  } catch (error) {
    if (client) {
      try {
        await client.query("ROLLBACK");
      } catch (rollbackError) {
        const rollbackErrorName =
          rollbackError instanceof Error ? rollbackError.name : "UnknownError";
        console.error(`Rollback failed (${rollbackErrorName}).`);
      }
    }
    throw error;
  } finally {
    client?.release();
    await pool.end();
  }
}

async function readJsonList(filename: string): Promise<JsonRecord[]> {
  const source = await readFile(join(seedDirectory, filename), "utf8");
  const parsed: unknown = JSON.parse(source);

  if (!Array.isArray(parsed) || !parsed.every(isRecord)) {
    throw new CliError(`${filename} must contain an array of objects.`);
  }

  return parsed;
}

function isRecord(value: unknown): value is JsonRecord {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function stringValue(
  record: JsonRecord,
  field: string,
  required = false,
): string {
  const value = record[field];
  if (typeof value !== "string" || (required && value.trim() === "")) {
    throw new CliError(`Seed data field "${field}" must be a string.`);
  }
  return value;
}

function stringList(record: JsonRecord, field: string): string[] {
  const value = record[field] ?? [];
  if (
    !Array.isArray(value) ||
    !value.every((item) => typeof item === "string" && item !== "")
  ) {
    throw new CliError(`Seed data field "${field}" must be a string array.`);
  }
  return value;
}

function positiveInteger(value: unknown, field: string): number {
  const parsed = typeof value === "number" ? value : Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < 1 || parsed > 9999) {
    throw new CliError(
      `Seed data field "${field}" must be an integer from 1 to 9999.`,
    );
  }
  return parsed;
}

function loggedDate(value: string): string | null {
  const match = /^(\d{4})\.(\d{2})$/.exec(value.trim());
  if (!match || Number(match[2]) < 1 || Number(match[2]) > 12) {
    return null;
  }
  return `${match[1]}-${match[2]}-01`;
}

async function seedDatabase(): Promise<void> {
  const projects = await readJsonList("projects.json");
  const skills = await readJsonList("skills.json");
  const buildLogs = await readJsonList("buildLogs.json");
  const pool = createPool();
  let client: PoolClient | undefined;

  try {
    client = await pool.connect();
    await client.query("BEGIN");

    const technologyIds = new Map<string, string>();
    for (const project of projects) {
      for (const name of stringList(project, "stack")) {
        if (technologyIds.has(name)) {
          continue;
        }
        const result = await client.query<{ id: string }>(
          `INSERT INTO technologies (name)
           VALUES ($1)
           ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name
           RETURNING id`,
          [name],
        );
        technologyIds.set(name, result.rows[0].id);
      }
    }

    for (const [index, project] of projects.entries()) {
      const displayOrder = positiveInteger(
        project.number ?? index + 1,
        "number",
      );
      const links = project.links ?? {};
      if (!isRecord(links)) {
        throw new CliError('Seed data field "links" must be an object.');
      }

      const result = await client.query<{ id: string }>(
        `INSERT INTO projects (
           display_order, slug, title, description, accent, visual,
           overview, problem, solution, features, architecture, github_url, demo_url
         ) VALUES (
           $1, $2, $3, $4, $5, $6, $7, $8, $9, $10::text[], $11::text[], $12, $13
         )
         ON CONFLICT (slug) DO UPDATE SET
           display_order = EXCLUDED.display_order,
           title = EXCLUDED.title,
           description = EXCLUDED.description,
           accent = EXCLUDED.accent,
           visual = EXCLUDED.visual,
           overview = EXCLUDED.overview,
           problem = EXCLUDED.problem,
           solution = EXCLUDED.solution,
           features = EXCLUDED.features,
           architecture = EXCLUDED.architecture,
           github_url = EXCLUDED.github_url,
           demo_url = EXCLUDED.demo_url
         RETURNING id`,
        [
          displayOrder,
          stringValue(project, "slug", true),
          stringValue(project, "title", true),
          stringValue(project, "description", true),
          stringValue(project, "accent", true),
          stringValue(project, "visual", true),
          typeof project.overview === "string" && project.overview !== ""
            ? project.overview
            : null,
          typeof project.problem === "string" && project.problem !== ""
            ? project.problem
            : null,
          typeof project.solution === "string" && project.solution !== ""
            ? project.solution
            : null,
          stringList(project, "features"),
          stringList(project, "architecture"),
          typeof links.github === "string" ? links.github : "",
          typeof links.demo === "string" ? links.demo : "",
        ],
      );
      const projectId = result.rows[0].id;
      await client.query(
        "DELETE FROM project_technologies WHERE project_id = $1",
        [projectId],
      );
      for (const [sortOrder, name] of stringList(project, "stack").entries()) {
        const technologyId = technologyIds.get(name);
        if (!technologyId) {
          throw new CliError(`Technology "${name}" was not registered.`);
        }
        await client.query(
          `INSERT INTO project_technologies (project_id, technology_id, sort_order)
           VALUES ($1, $2, $3)
           ON CONFLICT (project_id, technology_id)
           DO UPDATE SET sort_order = EXCLUDED.sort_order`,
          [projectId, technologyId, sortOrder],
        );
      }
    }

    let skillOrder = 0;
    for (const group of skills) {
      const category = stringValue(group, "title", true);
      for (const name of stringList(group, "items")) {
        skillOrder += 1;
        await client.query(
          `INSERT INTO skills (category, name, description, sort_order)
           VALUES ($1, $2, NULL, $3)
           ON CONFLICT (category, name) DO UPDATE SET
             description = EXCLUDED.description,
             sort_order = EXCLUDED.sort_order`,
          [category, name, skillOrder],
        );
      }
    }

    let logOrder = 0;
    const skippedDates: string[] = [];
    for (const log of buildLogs) {
      const rawDate = stringValue(log, "date");
      const date = loggedDate(rawDate);
      if (!date) {
        skippedDates.push(rawDate);
        continue;
      }
      logOrder += 1;
      await client.query(
        `INSERT INTO build_logs (title, tag, logged_at, sort_order, is_public)
         VALUES ($1, $2, $3, $4, TRUE)
         ON CONFLICT (logged_at, title) DO NOTHING`,
        [
          stringValue(log, "title", true),
          stringValue(log, "tag", true),
          date,
          logOrder,
        ],
      );
    }

    await client.query("COMMIT");
    console.log(
      `Seed completed: ${projects.length} projects, ${skills.length} skill groups, ${buildLogs.length - skippedDates.length} build logs.`,
    );
    if (skippedDates.length > 0) {
      console.warn(
        `Skipped build-log dates with unsupported format: ${skippedDates.join(", ")}`,
      );
    }
    console.warn(
      "Seed upserts project and skill fields; existing build logs are left unchanged. Rows absent from JSON are not deleted.",
    );
  } catch (error) {
    if (client) {
      try {
        await client.query("ROLLBACK");
      } catch (rollbackError) {
        const rollbackErrorName =
          rollbackError instanceof Error ? rollbackError.name : "UnknownError";
        console.error(`Rollback failed (${rollbackErrorName}).`);
      }
    }
    throw error;
  } finally {
    client?.release();
    await pool.end();
  }
}

async function main(): Promise<void> {
  assertLocalExecution();
  const [command, ...options] = process.argv.slice(2);

  if (command === "migrate") {
    if (options.some((option) => option !== "--status")) {
      usage();
    }
    await runMigrations(options.includes("--status"));
    return;
  }

  if (command === "seed") {
    if (options.length !== 1 || options[0] !== "--confirm") {
      console.error(
        "Seeding can overwrite existing project and skill content. Re-run with --confirm to proceed.",
      );
      process.exitCode = 2;
      return;
    }
    await seedDatabase();
    return;
  }

  usage();
}

main().catch((error: unknown) => {
  if (error instanceof CliError) {
    console.error(`Database CLI failed: ${error.message}`);
    process.exitCode = 1;
    return;
  }

  const errorName = error instanceof Error ? error.name : "UnknownError";
  const errorCode =
    isRecord(error) && typeof error.code === "string" ? `, code ${error.code}` : "";
  console.error(
    `Database CLI failed (${errorName}${errorCode}). Detailed connection errors are hidden.`,
  );
  process.exitCode = 1;
});
