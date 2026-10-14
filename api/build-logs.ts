import { query } from "../lib/api/database.js";
import { getOnly, json, type ApiRequest, type ApiResponse } from "../lib/api/http.js";
import { BUILD_LOG_SELECT, serializeBuildLog, type BuildLogRow } from "../lib/api/build-logs.js";

export default getOnly(async (_request: ApiRequest, response: ApiResponse) => {
  const rows = await query<BuildLogRow>(
    `${BUILD_LOG_SELECT}
     WHERE is_public = TRUE
     ORDER BY sort_order, logged_at DESC, id`,
  );
  json(response, 200, rows.map(serializeBuildLog));
});
