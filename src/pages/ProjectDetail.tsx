import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";

import { fetchProjectBySlug } from "../api/projects";
import type { Project } from "../types/content";

const backLinkClass = "group inline-flex items-center gap-2 rounded border border-cyan-400/25 bg-cyan-400/5 px-4 py-3 font-mono text-xs text-cyan-200 transition hover:border-cyan-300/50 hover:bg-cyan-400/10 hover:text-cyan-100";

const overviewCardClass = "rounded-xl border border-cyan-400/20 bg-[#0b1220]/80 p-5 sm:p-6";
const cardClass = "rounded-xl border border-white/6 bg-[#0b1220]/75 p-5 sm:p-6";
const cardLabelClass = "mb-4 font-mono text-[10px] tracking-[0.18em] text-cyan-400 sm:text-xs";

const statusClass = "rounded border border-white/6 bg-[#0b1220]/75 px-5 py-6 font-mono text-[10px] tracking-[0.18em] text-slate-500 sm:text-xs";

type ProjectStatus = "loading" | "success" | "error" | "notfound";

function ProjectDetail() {
  // ルート定義（App.tsx の /works/:id）の param 名は id だが、値は slug が入る
  const { id: slug } = useParams();
  const [status, setStatus] = useState<ProjectStatus>("loading");
  const [project, setProject] = useState<Project | null>(null);

  useEffect(() => {
    if (slug === undefined || slug === "") {
      setProject(null);
      setStatus("notfound");

      return;
    }

    let cancelled = false;

    setStatus("loading");

    fetchProjectBySlug(slug)
      .then((data) => {
        if (cancelled) {
          return;
        }

        if (data === null) {
          setProject(null);
          setStatus("notfound");

          return;
        }

        setProject(data);
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
  }, [slug]);

  if (status === "loading") {
    return (
      <main className="mx-auto max-w-4xl px-5 py-32 lg:px-8">
        <p className={statusClass}>[LOADING] PROJECT</p>
      </main>
    );
  }

  if (status === "error") {
    return (
      <main className="mx-auto max-w-4xl px-5 py-32 lg:px-8">
        <p className={statusClass}>[COMING SOON] PROJECT 準備中</p>
        <Link to="/" className={`${backLinkClass} mt-6`}>
          <span className="text-cyan-400 transition group-hover:text-cyan-300">←</span>
          <span>BACK TO WORKS</span>
        </Link>
      </main>
    );
  }

  if (status === "notfound" || project === null) {
    return (
      <main className="mx-auto max-w-4xl px-5 py-32 lg:px-8">
        <h1 className="text-4xl font-black">Project Not Found</h1>
        <Link to="/" className={`${backLinkClass} mt-6`}>
          <span className="text-cyan-400 transition group-hover:text-cyan-300">←</span>
          <span>BACK TO WORKS</span>
        </Link>
      </main>
    );
  }

  const overview = project.overview?.trim();
  const problem = project.problem?.trim();
  const solution = project.solution?.trim();
  const sections = [
    {
      title: "Features",
      body: project.features?.filter((item) => item.trim()) ?? [],
    },
    {
      title: "Architecture",
      body: project.architecture?.filter((item) => item.trim()) ?? [],
    },
  ].filter((section) => section.body.length > 0);
  const hasProblemAndSolution = Boolean(problem && solution);

  const hasLinks = Boolean(project.links?.github || project.links?.demo);

  return (
    <main className="mx-auto max-w-4xl px-5 py-32 lg:px-8">
      <div className="mb-6">
        <Link to="/" className={backLinkClass}>
          <span className="text-cyan-400 transition group-hover:text-cyan-300">←</span>
          <span>BACK TO WORKS</span>
        </Link>
      </div>

      <div className="border-b border-white/6 pb-10">
        <p className="font-mono text-xs text-cyan-400">
          {project.accent}
        </p>

        <h1 className="mt-4 text-4xl font-black tracking-tight sm:text-5xl">
          {project.title}
        </h1>

        <p className="mt-6 max-w-3xl text-sm leading-7 text-slate-400 sm:text-base">
          {project.description}
        </p>

        <div className="mt-8 flex flex-wrap gap-2">
          {project.stack.map((item) => (
            <span
              key={item}
              className="rounded border border-cyan-400/15 px-3 py-1 font-mono text-[10px] text-cyan-300 sm:text-[11px]"
            >
              {item}
            </span>
          ))}
        </div>

        {hasLinks && (
          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            {project.links?.github && (
              <a
                href={project.links.github}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center justify-center rounded border border-cyan-400/30 bg-cyan-400/10 px-4 py-2 text-xs font-bold text-cyan-300 transition hover:border-cyan-300 hover:bg-cyan-400/15"
              >
                GitHub
              </a>
            )}

            {project.links?.demo && (
              <a
                href={project.links.demo}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center justify-center rounded border border-slate-700 bg-slate-900/80 px-4 py-2 text-xs font-bold text-slate-200 transition hover:border-cyan-400/50 hover:text-cyan-300"
              >
                Demo
              </a>
            )}
          </div>
        )}
      </div>

      <div className="mt-12 space-y-8">
        {overview && (
          <section className={overviewCardClass}>
            <div className={cardLabelClass}>
              [OVERVIEW]
            </div>
            <p className="text-sm leading-7 text-slate-200 sm:text-[15px]">
              {overview}
            </p>
          </section>
        )}

        {(problem || solution) && (
          <div className={`grid gap-8 ${hasProblemAndSolution ? "lg:grid-cols-2" : ""}`}>
            {problem && (
              <section className={cardClass}>
                <div className={cardLabelClass}>
                  [PROBLEM]
                </div>
                <p className="text-sm leading-7 text-slate-300 sm:text-[15px]">
                  {problem}
                </p>
              </section>
            )}

            {solution && (
              <section className={cardClass}>
                <div className={cardLabelClass}>
                  [SOLUTION]
                </div>
                <p className="text-sm leading-7 text-slate-300 sm:text-[15px]">
                  {solution}
                </p>
              </section>
            )}
          </div>
        )}

        {sections.map((section) => (
          <section key={section.title} className={cardClass}>
            <div className={cardLabelClass}>
              [{section.title.toUpperCase()}]
            </div>

            <ul className="space-y-3 text-sm leading-7 text-slate-300 sm:text-[15px]">
              {section.body.map((item: string) => (
                <li key={item} className="flex gap-3">
                  <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-400" />
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    </main>
  );
}

export default ProjectDetail;
