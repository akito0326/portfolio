import { query } from "../../lib/api/database";
import { badRequest, getOnly, json, type ApiRequest, type ApiResponse } from "../../lib/api/http";
import { PROJECT_SELECT, serializeProject, type ProjectRow } from "../../lib/api/projects";

export default getOnly(async (request: ApiRequest, response: ApiResponse) => {
  const rawId = request.query.id;
  let numericId: bigint;

  try {
    if (typeof rawId !== "string" || !/^\d+$/.test(rawId)) {
      throw new Error("Invalid ID");
    }
    numericId = BigInt(rawId);
  } catch {
    badRequest(response, "invalid_project_id", "project id は 1 以上の整数で指定してください。");
    return;
  }

  if (numericId < 1n) {
    badRequest(response, "invalid_project_id", "project id は 1 以上の整数で指定してください。");
    return;
  }

  const normalizedId = numericId > 9_223_372_036_854_775_807n
    ? "9223372036854775807"
    : numericId.toString();
  const rows = await query<ProjectRow>(`${PROJECT_SELECT} WHERE p.id = $1`, [normalizedId]);
  const project = rows[0];

  if (!project) {
    json(response, 404, {
      error: {
        code: "project_not_found",
        message: `project id=${normalizedId} は存在しません。`,
      },
    });
    return;
  }

  json(response, 200, serializeProject(project));
});
