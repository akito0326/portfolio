export type ProjectRow = {
  id: string | number;
  display_order: number;
  slug: string;
  title: string;
  description: string;
  accent: string;
  visual: string;
  overview: string | null;
  problem: string | null;
  solution: string | null;
  features: string[] | null;
  architecture: string[] | null;
  stack: string[] | null;
  github_url: string;
  demo_url: string;
  created_at: string | null;
  updated_at: string | null;
};

export const PROJECT_SELECT = `
  SELECT
    p.id,
    p.display_order,
    p.slug,
    p.title,
    p.description,
    p.accent,
    p.visual,
    p.overview,
    p.problem,
    p.solution,
    to_json(p.features) AS features,
    to_json(p.architecture) AS architecture,
    COALESCE((
      SELECT json_agg(t.name ORDER BY pt.sort_order, t.name)
      FROM project_technologies pt
      JOIN technologies t ON t.id = pt.technology_id
      WHERE pt.project_id = p.id
    ), '[]'::json) AS stack,
    p.github_url,
    p.demo_url,
    p.created_at::text AS created_at,
    p.updated_at::text AS updated_at
  FROM projects p
`;

export function serializeProject(row: ProjectRow) {
  const project = {
    id: Number(row.id),
    number: String(row.display_order).padStart(2, "0"),
    slug: row.slug,
    title: row.title,
    description: row.description,
    stack: row.stack ?? [],
    accent: row.accent,
    visual: row.visual,
  } as Record<string, unknown>;

  for (const [key, value] of [
    ["overview", row.overview],
    ["problem", row.problem],
    ["solution", row.solution],
  ] as const) {
    if (value !== null) {
      project[key] = value;
    }
  }

  project.features = row.features ?? [];
  project.architecture = row.architecture ?? [];
  project.links = { github: row.github_url, demo: row.demo_url };
  project.created_at = row.created_at;
  project.updated_at = row.updated_at;

  return project;
}
