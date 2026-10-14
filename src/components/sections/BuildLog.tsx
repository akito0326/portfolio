import { useEffect, useState } from "react";

import SectionHeading from "../ui/SectionHeading";
import { fetchBuildLogs } from "../../api/buildLogs";
import type { BuildLog as BuildLogEntry } from "../../types/content";

type FetchStatus = "loading" | "success" | "error";

const statusClass =
  "rounded border border-white/6 bg-[#0b1220]/75 px-5 py-6 font-mono text-[10px] tracking-[0.18em] text-slate-500 sm:text-xs";

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
      className="mx-auto max-w-6xl px-5 py-24 lg:px-8 lg:py-32"
    >
      <SectionHeading
        number="02"
        eyebrow="BUILD LOG"
        title="作っているもの。"
        description="現在進行中の開発や、技術的に試していること。"
      />

      {status === "loading" && <p className={statusClass}>[LOADING] BUILD LOGS</p>}

      {status === "error" && <p className={statusClass}>[ERROR] FAILED TO LOAD BUILD LOGS</p>}

      {status === "success" && logs.length === 0 && (
        <p className={statusClass}>[EMPTY] NO BUILD LOGS</p>
      )}

      {status === "success" && logs.length > 0 && (
        <div className="border-y border-white/6">
          {logs.map((log) => (
            <div
              key={`${log.date}-${log.title}`}
              className="grid gap-3 border-b border-white/6 py-5 last:border-b-0 sm:grid-cols-[100px_1fr_auto] sm:items-center"
            >
              <span className="font-mono text-[10px] text-slate-600">
                {log.date}
              </span>

              <span className="text-sm text-slate-300">
                {log.title}
              </span>

              <span className="font-mono text-[9px] uppercase tracking-wider text-cyan-400">
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