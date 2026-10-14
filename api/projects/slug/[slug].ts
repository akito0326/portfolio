import { query } from "../../../lib/api/database";
import { getOnly, badRequest, json, type ApiRequest, type ApiResponse } from "../../../lib/api/http";
import { PROJECT_SELECT, serializeProject, type ProjectRow } from "../../../lib/api/projects";

export default getOnly(async (request: ApiRequest, response: ApiResponse) => {
  const slug = request.query.slug;

  if (typeof slug !== "string" || slug.trim() === "") {
    badRequest(response, "invalid_project_slug", "project slug を指定してください。");
    return;
  }

  const rows = await query<ProjectRow>(`${PROJECT_SELECT} WHERE p.slug = $1`, [slug.trim()]);
  const project = rows[0];

  if (!project) {
    json(response, 404, {
      error: {
        code: "project_not_found",
        message: `project slug=${slug.trim()} は存在しません。`,
      },
    });
    return;
  }

  json(response, 200, serializeProject(project));
});
