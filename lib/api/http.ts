import { DatabaseUnavailableError } from "./database.js";

export interface ApiRequest {
  method?: string;
  query: Record<string, string | string[] | undefined>;
}

export interface ApiResponse {
  status(code: number): ApiResponse;
  setHeader(name: string, value: string): ApiResponse;
  json(payload: unknown): ApiResponse;
}

type ApiHandler = (request: ApiRequest, response: ApiResponse) => Promise<void>;

export function json(
  response: ApiResponse,
  status: number,
  payload: unknown,
): void {
  response.status(status).setHeader("Content-Type", "application/json; charset=utf-8").json(payload);
}

export function getOnly(handler: ApiHandler): ApiHandler {
  return async (request, response) => {
    if (request.method !== "GET") {
      response.setHeader("Allow", "GET");
      json(response, 405, {
        error: { code: "method_not_allowed", message: "Method not allowed." },
      });
      return;
    }

    try {
      await handler(request, response);
    } catch (error) {
      if (error instanceof DatabaseUnavailableError) {
        json(response, 503, {
          error: {
            code: "database_unavailable",
            message: "データベースへ接続できませんでした。",
          },
        });
        return;
      }

      console.error("API request failed", {
        error: error instanceof Error ? error.name : "unknown",
      });
      json(response, 500, {
        error: {
          code: "internal_error",
          message: "サーバー内部エラーが発生しました。",
        },
      });
    }
  };
}

export function badRequest(
  response: ApiResponse,
  code: string,
  message: string,
): void {
  json(response, 400, { error: { code, message } });
}
