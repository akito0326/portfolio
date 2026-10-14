/**
 * Build Log API 通信
 *
 * 責務: HTTP 通信とレスポンス処理
 * 使用元: components/sections/BuildLog.tsx（一覧）
 * 非担当: UI の状態管理・表示（呼び出し側が行う）
 *
 * ルール:
 * - 非 2xx は throw する
 * - レスポンスは date / title / tag を含み、src/types/content.ts の BuildLog として扱える
 */

import type { BuildLog } from "../types/content";

const API_BASE_URL: string = import.meta.env.VITE_API_BASE_URL ?? "/api";

const REQUEST_HEADERS = { Accept: "application/json" };

/**
 * GET /api/build-logs
 * 公開分のみを表示順で返す。
 */
export async function fetchBuildLogs(): Promise<BuildLog[]> {
  const path = `${API_BASE_URL}/build-logs`;
  const response = await fetch(path, { headers: REQUEST_HEADERS });

  if (!response.ok) {
    throw new Error(`GET ${path} failed: ${response.status} ${response.statusText}`);
  }

  return (await response.json()) as BuildLog[];
}