import { DatabaseUnavailableError, query } from "../lib/api/database.js";
import { getOnly, json, type ApiRequest, type ApiResponse } from "../lib/api/http.js";

export default getOnly(async (_request: ApiRequest, response: ApiResponse) => {
  try {
    await query("SELECT 1");
    json(response, 200, { status: "ok", database: "connected" });
  } catch (error) {
    if (!(error instanceof DatabaseUnavailableError)) {
      throw error;
    }

    json(response, 503, { status: "error", database: "disconnected" });
  }
});
