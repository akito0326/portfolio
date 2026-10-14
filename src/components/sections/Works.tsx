import { useEffect, useState } from "react";

import SectionHeading from "../ui/SectionHeading";
import ProjectCard from "./ProjectCard";
import { fetchProjects } from "../../api/projects";
import type { Project } from "../../types/content";

type FetchStatus = "loading" | "success" | "error";

const statusClass =
  "rounded border border-white/6 bg-[#0b1220]/75 px-5 py-6 font-mono text-[10px] tracking-[0.18em] text-slate-500 sm:text-xs";

function Works() {
  const [status, setStatus] = useState<FetchStatus>("loading");
  const [projects, setProjects] = useState<Project[]>([]);

  useEffect(() => {
    let cancelled = false;

    fetchProjects()
      .then((data) => {
        if (cancelled) {
          return;
        }

        setProjects(data);
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
    <section id="works" className="mx-auto max-w-6xl px-5 py-24 lg:px-8 lg:py-32">
      <SectionHeading
        number="01"
        eyebrow="SELECTED WORKS"
        title="作ったもの。仕組みにしたもの。"
        description="学習のためだけではなく、実際の業務や課題を想定して設計したWebアプリケーション。"
      />

      {status === "loading" && <p className={statusClass}>[LOADING] PROJECTS</p>}

      {status === "error" && <p className={statusClass}>[COMING SOON] PROJECTS 準備中</p>}

      {status === "success" && projects.length === 0 && (
        <p className={statusClass}>[EMPTY] NO PROJECTS</p>
      )}

      {status === "success" && projects.length > 0 && (
        <div className="grid gap-5 lg:grid-cols-3">
          {projects.map((project) => (
            <ProjectCard key={project.number} project={project} />
          ))}
        </div>
      )}
    </section>
  );
}

export default Works