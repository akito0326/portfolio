import { ChevronRight } from "lucide-react";
import { Link } from "react-router-dom";
import type { Project } from "../../types/content";

function ProjectCard({ project }: { project: Project }) {
  return (
    <Link to={`/works/${project.slug}`} className="theme-border-strong theme-surface group overflow-hidden rounded-xl border transition duration-300 hover:-translate-y-1 hover:border-cyan-400/30 hover:shadow-[0_18px_60px_rgba(0,0,0,.28)]">
      <div className="theme-border-strong theme-surface-muted relative aspect-16/10 overflow-hidden border-b">
        <div className="absolute inset-0 bg-[linear-gradient(rgba(34,211,238,.2)_1px,transparent_1px),linear-gradient(90deg,rgba(34,211,238,.2)_1px,transparent_1px)] opacity-20 [bg-size:28px_28px]" />

        <div className="absolute left-4 top-4 font-mono text-xs tracking-[0.18em] text-cyan-400">
          {project.accent}
        </div>

        <div className="theme-surface-raised absolute inset-x-6 bottom-6 rounded border border-cyan-400/20 p-4 backdrop-blur">
          <div className="mb-4 flex gap-2">
            <span className="h-1.5 w-1.5 rounded-full bg-cyan-400" />
            <span className="h-1.5 w-1.5 rounded-full bg-slate-600" />
            <span className="h-1.5 w-1.5 rounded-full bg-slate-600" />
          </div>

          <div className="theme-text-primary font-mono text-xl font-bold tracking-tight">
            {project.visual}
          </div>

          <div className="mt-2 h-1 w-2/3 rounded bg-cyan-400/50" />
          <div className="mt-2 h-1 w-1/2 rounded bg-slate-700" />
        </div>

        <div className="theme-text-muted absolute right-4 top-4 font-mono text-xs">
          {project.number}
        </div>
      </div>

      <div className="p-4 sm:p-6">
        <h3 className="text-xl font-bold">{project.title}</h3>

        <p className="theme-text-secondary mt-4 min-h-20 text-base leading-relaxed tracking-wide">
          {project.description}
        </p>

        <div className="mt-6 flex flex-wrap gap-2">
          {project.stack.map((item) => (
            <span
              key={item}
              className="theme-text-secondary rounded border border-cyan-400/15 bg-cyan-400/4 px-2 py-1 font-mono text-xs"
            >
              {item}
            </span>
          ))}
        </div>

        <div className="theme-text-secondary mt-6 inline-flex min-h-11 items-center gap-1 py-2 text-base font-bold tracking-wider transition group-hover:text-cyan-300">
          VIEW PROJECT
          <ChevronRight size={13} />
        </div>
      </div>
    </Link>
  );
}

export default ProjectCard;