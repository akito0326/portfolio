import { Pool, type QueryResultRow } from "pg";

export class DatabaseUnavailableError extends Error {
  constructor() {
    super("Database unavailable");
    this.name = "DatabaseUnavailableError";
  }
}

let pool: Pool | undefined;

function getPool(): Pool {
  if (pool) {
    return pool;
  }

  const connectionString = process.env.POSTGRES_URL;

  if (!connectionString) {
    throw new DatabaseUnavailableError();
  }

  pool = new Pool({
    connectionString,
    max: 1,
    connectionTimeoutMillis: 5_000,
    idleTimeoutMillis: 5_000,
  });

  return pool;
}

export async function query<Row extends QueryResultRow>(
  text: string,
  values: readonly unknown[] = [],
): Promise<Row[]> {
  try {
    const result = await getPool().query<Row>(text, [...values]);
    return result.rows;
  } catch (error) {
    console.error("PostgreSQL request failed", {
      error: error instanceof Error ? error.name : "unknown",
    });
    throw new DatabaseUnavailableError();
  }
}
