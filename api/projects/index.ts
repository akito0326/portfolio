import { query } from "../../lib/api/database";
import { getOnly, json, type ApiRequest, type ApiResponse } from "../../lib/api/http";
import { PROJECT_SELECT, serializeProject, type ProjectRow } from "../../lib/api/projects";

export default getOnly(async (_request: ApiRequest, response: ApiResponse) => {
  const rows = await query<ProjectRow>(
    `${PROJECT_SELECT} ORDER BY p.display_order`,
  );
  json(response, 200, rows.map(serializeProject));
});
