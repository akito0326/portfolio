#!/usr/bin/env node
/**
 * 開発環境の一括起動（PostgreSQL → PHP API → Vite）
 *
 * 責務: 3プロセスの起動と、Ctrl+C による一括停止
 * 依存: Node.js 標準モジュールのみ（concurrently 等は使わない）
 *
 * 使い方:
 *   node scripts/dev.mjs            DB → API → Vite
 *   node scripts/dev.mjs --db-only  DB のみ
 *   node scripts/dev.mjs --no-db    DB を起動せず API → Vite
 *
 * 環境変数 API_PORT / WEB_PORT で待ち受けポートを変更できる。
 * 既に使用中のポートはスキップする（別ターミナルで起動済みのものを尊重）。
 */

import { spawn } from "node:child_process";
import fs from "node:fs";
import net from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const BACKEND_DIR = path.join(ROOT, "backend");
const VITE_ENTRY = path.join(ROOT, "node_modules", "vite", "bin", "vite.js");

const API_HOST = "127.0.0.1";
const API_PORT = Number(process.env.API_PORT ?? 8080);
const WEB_PORT = Number(process.env.WEB_PORT ?? 5173);

const isWindows = process.platform === "win32";
const argFlags = new Set(process.argv.slice(2));
const dbOnly = argFlags.has("--db-only");
const skipDb = argFlags.has("--no-db");

const managed = new Map();
let shuttingDown = false;

function log(tag, message) {
  String(message)
    .split(/\r?\n/)
    .filter((line) => line.trim() !== "")
    .forEach((line) => process.stdout.write(`[${tag.padEnd(4)}] ${line}\n`));
}

/** backend/.env から値を読む（DB_PORT を docker-compose.yml と共有するため） */
function readEnvValue(key, fallback) {
  try {
    const lines = fs.readFileSync(path.join(BACKEND_DIR, ".env"), "utf8").split(/\r?\n/);
    const line = lines.find((row) => row.trim().startsWith(`${key}=`));

    if (!line) {
      return fallback;
    }

    const value = line.slice(line.indexOf("=") + 1).trim().replace(/^["']|["']$/g, "");

    return value === "" ? fallback : value;
  } catch {
    return fallback;
  }
}

const DB_HOST = readEnvValue("DB_HOST", "127.0.0.1");
const DB_PORT = Number(readEnvValue("DB_PORT", "5432"));
const DB_LABEL = `${DB_HOST}:${DB_PORT}`;

function isPortInUse(port, host = API_HOST) {
  return new Promise((resolve) => {
    const socket = net.connect({ port, host });
    const finish = (value) => {
      socket.destroy();
      resolve(value);
    };

    socket.setTimeout(400);
    socket.once("connect", () => finish(true));
    socket.once("timeout", () => finish(false));
    socket.once("error", () => finish(false));
  });
}

async function waitForPort(port, host, timeoutMs) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    if (await isPortInUse(port, host)) {
      return true;
    }

    await new Promise((resolve) => setTimeout(resolve, 500));
  }

  return false;
}

/** 子プロセスの出力を [tag] 付きで1画面に流す */
function pipeWithTag(stream, tag) {
  let buffer = "";

  stream.setEncoding("utf8");
  stream.on("data", (chunk) => {
    buffer += chunk;
    const lines = buffer.split(/\r?\n/);
    buffer = lines.pop() ?? "";
    log(tag, lines.join("\n"));
  });
  stream.on("close", () => {
    if (buffer.trim() !== "") {
      log(tag, buffer);
      buffer = "";
    }
  });
}

function killTree(child) {
  return new Promise((resolve) => {
    if (child.exitCode !== null || child.signalCode !== null) {
      resolve();

      return;
    }

    child.once("exit", () => resolve());
    setTimeout(() => resolve(), 5000);

    if (isWindows && child.pid !== undefined) {
      // Windows はプロセスツリーごと終了させる（taskkill /T）
      spawn("taskkill", ["/pid", String(child.pid), "/T", "/F"], { stdio: "ignore" });
    } else {
      child.kill("SIGTERM");
      setTimeout(() => {
        if (child.exitCode === null) {
          child.kill("SIGKILL");
        }
      }, 3000);
    }
  });
}

async function shutdown(code = 0) {
  if (shuttingDown) {
    return;
  }

  shuttingDown = true;
  process.stdout.write("\n");

  if (managed.size > 0) {
    log("dev", "停止しています ...");
    await Promise.all([...managed.values()].map((child) => killTree(child)));
  }

  log("dev", "停止しました");
  process.exit(code);
}

process.on("SIGINT", () => void shutdown(0));
process.on("SIGTERM", () => void shutdown(0));

if (isWindows) {
  process.on("SIGBREAK", () => void shutdown(0));
}

function spawnTagged(tag, command, commandArgs, cwd) {
  const child = spawn(command, commandArgs, {
    cwd,
    stdio: ["ignore", "pipe", "pipe"],
  });

  pipeWithTag(child.stdout, tag);
  pipeWithTag(child.stderr, tag);

  return child;
}

function runOnce(tag, command, commandArgs, cwd) {
  return new Promise((resolve) => {
    const child = spawnTagged(tag, command, commandArgs, cwd);

    child.once("error", (error) => {
      log(tag, `実行できませんでした: ${error.message}`);
      resolve(1);
    });
    child.once("exit", (code) => resolve(code ?? 1));
  });
}

async function startDatabase() {
  if (await isPortInUse(DB_PORT, DB_HOST)) {
    log("db", `起動済み (${DB_LABEL})`);

    return;
  }

  log("db", `起動しています ... (${DB_LABEL})`);

  const code = await runOnce("db", "docker", ["compose", "up", "-d"], BACKEND_DIR);

  if (code !== 0) {
    log("db", "docker compose up に失敗しました。Docker Desktop が起動しているか確認してください。");

    return;
  }

  const ready = await waitForPort(DB_PORT, DB_HOST, 30000);
  log("db", ready ? `準備完了 (${DB_LABEL})` : "応答待ちのままです（起動に時間がかかっている可能性があります）");
}

/** 終了時に全体を止めるため、子プロセスの exit を監視して登録する */
function supervise(tag, child) {
  child.once("error", (error) => {
    log(tag, `起動できませんでした: ${error.message}`);
  });

  child.once("exit", (code) => {
    if (shuttingDown) {
      return;
    }

    log(tag, `終了しました (exit=${code ?? "?"})`);
    void shutdown(code ?? 1);
  });

  managed.set(tag, child);
}

async function startApi() {
  if (await isPortInUse(API_PORT)) {
    log("api", `ポート ${API_PORT} は使用中のためスキップします（Ctrl+C では停止しません）`);

    return;
  }

  const child = spawnTagged(
    "api",
    "php",
    [
      "-d", "extension=pdo_pgsql",
      "-S", `${API_HOST}:${API_PORT}`,
      "-t", "backend/public",
      "backend/public/index.php",
    ],
    ROOT,
  );

  supervise("api", child);
  log("api", `起動中 ... http://${API_HOST}:${API_PORT}`);
}

async function startVite() {
  if (await isPortInUse(WEB_PORT)) {
    log("vite", `ポート ${WEB_PORT} は使用中のためスキップします（Ctrl+C では停止しません）`);

    return;
  }

  const child = spawnTagged(
    "vite",
    process.execPath,
    [VITE_ENTRY, "--host", API_HOST, "--port", String(WEB_PORT), "--strictPort"],
    ROOT,
  );

  supervise("vite", child);
  log("vite", `起動中 ... http://${API_HOST}:${WEB_PORT}`);
}

async function main() {
  process.stdout.write("AKITO DEV — 開発環境を起動します\n\n");

  if (dbOnly) {
    await startDatabase();
  } else {
    if (!skipDb) {
      await startDatabase();
    }

    await startApi();
    await startVite();
  }

  if (managed.size === 0) {
    log("dev", "起動が必要なプロセスはありませんでした。");

    process.exit(0);
  }

  process.stdout.write("\n");
  log("dev", "Ctrl+C で API / Vite をまとめて停止します。");
}

void main();