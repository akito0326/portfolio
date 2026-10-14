export type BuildLogRow = {
  id: string | number;
  title: string;
  tag: string;
  logged_at: string;
  sort_order: number;
  is_public: boolean;
};

export const BUILD_LOG_SELECT = `
  SELECT id, title, tag, logged_at::text AS logged_at, sort_order, is_public
  FROM build_logs
`;

export function serializeBuildLog(row: BuildLogRow) {
  const dateMatch = /^(\d{4})-(\d{2})/.exec(row.logged_at);

  return {
    id: Number(row.id),
    date: dateMatch ? `${dateMatch[1]}.${dateMatch[2]}` : row.logged_at,
    title: row.title,
    tag: row.tag,
    logged_at: row.logged_at,
    sort_order: row.sort_order,
    is_public: row.is_public,
  };
}
