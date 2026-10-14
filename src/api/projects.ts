/**
 * Project API 通信
 *
 * 責務: HTTP 通信とレスポンス処理
 * 使用元: components/sections/Works.tsx（一覧） / pages/ProjectDetail.tsx（詳細）
 * 非担当: UI の状態管理・表示（呼び出し側が行う）
 *
 * ルール:
 * - 404 は「該当なし」として null を返す（エラー扱いしない）
 * - その他の HTTP エラーは throw する
 *
 * 汎用 API クライアントは作らない。今回必要な通信はこの2つだけのため。
 */

import type { Project } from "../types/content";

const API_BASE_URL: string = import.meta.env.VITE_API_BASE_URL ?? "/api";

const REQUEST_HEADERS = { Accept: "application/json" };

/**
 * GET /api/projects
 */
export async function fetchProjects(): Promise<Project[]> {
  const path = `${API_BASE_URL}/projects`;
  const response = await fetch(path, { headers: REQUEST_HEADERS });

  if (!response.ok) {
    throw new Error(`GET ${path} failed: ${response.status} ${response.statusText}`);
  }

  return (await response.json()) as Project[];
}

/**
 * GET /api/projects/slug/{slug}
 * プロジェクトが存在しない場合（404）は null を返す。
 */
export async function fetchProjectBySlug(slug: string): Promise<Project | null> {
  const path = `${API_BASE_URL}/projects/slug/${encodeURIComponent(slug)}`;
  const response = await fetch(path, { headers: REQUEST_HEADERS });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(`GET ${path} failed: ${response.status} ${response.statusText}`);
  }

  return (await response.json()) as Project;
}
