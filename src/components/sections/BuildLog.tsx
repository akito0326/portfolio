import { useEffect, useState } from "react";

import SectionHeading from "../ui/SectionHeading";
import { fetchBuildLogs } from "../../api/buildLogs";
import type { BuildLog as BuildLogEntry } from "../../types/content";

type FetchStatus = "loading" | "success" | "error";

const statusClass =
  "theme-border theme-surface theme-text-muted rounded border px-4 py-6 font-mono text-xs tracking-[0.18em]";

function BuildLog() {
  const [status, setStatus] = useState<FetchStatus>("loading");
  const [logs, setLogs] = useState<BuildLogEntry[]>([]);

  useEffect(() => {
    let cancelled = false;

    fetchBuildLogs()
      .then((data) => {
        if (cancelled) {
          return;
        }

        setLogs(data);
        setStatus("success");
      })
      .catch(() => {
        if (cancelled) {
          return;
        }

        setStatus("error");
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <section
      id="log"
      className="mx-auto max-w-6xl px-4 py-24 lg:px-8 lg:py-32"
    >
      <SectionHeading
        number="02"
        eyebrow="BUILD LOG"
        title="作っているもの。"
        description="現在進行中の開発や、技術的に試していること。"
      />

      {status === "loading" && <p className={statusClass}>[LOADING] BUILD LOGS</p>}

      {status === "error" && <p className={statusClass}>[COMING SOON] BUILD LOGS 準備中</p>}

      {status === "success" && logs.length === 0 && (
        <p className={statusClass}>[EMPTY] NO BUILD LOGS</p>
      )}

      {status === "success" && logs.length > 0 && (
        <div className="theme-border border-y">
          {logs.map((log) => (
            <div
              key={`${log.date}-${log.title}`}
              className="theme-border grid gap-4 border-b py-4 last:border-b-0 sm:grid-cols-[100px_1fr_auto] sm:items-center"
            >
              <span className="theme-text-muted font-mono text-sm">
                {log.date}
              </span>

              <span className="theme-text-secondary text-base leading-relaxed tracking-wide">
                {log.title}
              </span>

              <span className="font-mono text-xs uppercase tracking-wider text-cyan-400">
                {log.tag}
              </span>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

export default BuildLog;