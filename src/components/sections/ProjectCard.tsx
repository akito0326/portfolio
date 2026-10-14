import { ChevronRight } from "lucide-react";
import { Link } from "react-router-dom";
import type { Project } from "../../types/content";

function ProjectCard({ project }: { project: Project }) {
  return (
    <Link to={`/works/${project.slug}`} className="group overflow-hidden rounded-xl border border-slate-800 bg-[#101827] transition duration-300 hover:-translate-y-1 hover:border-cyan-400/30 hover:shadow-[0_18px_60px_rgba(0,0,0,.28)]">
      <div className="relative aspect-16/10 overflow-hidden border-b border-slate-800 bg-[#0b1220]">
        <div className="absolute inset-0 bg-[linear-gradient(rgba(34,211,238,.2)_1px,transparent_1px),linear-gradient(90deg,rgba(34,211,238,.2)_1px,transparent_1px)] opacity-20 [bg-size:28px_28px]" />

        <div className="absolute left-5 top-5 font-mono text-[8px] tracking-[0.18em] text-cyan-400">
          {project.accent}
        </div>

        <div className="absolute inset-x-6 bottom-6 rounded border border-cyan-400/20 bg-slate-950/70 p-4 backdrop-blur">
          <div className="mb-3 flex gap-1.5">
            <span className="h-1.5 w-1.5 rounded-full bg-cyan-400" />
            <span className="h-1.5 w-1.5 rounded-full bg-slate-600" />
            <span className="h-1.5 w-1.5 rounded-full bg-slate-600" />
          </div>

          <div className="font-mono text-lg font-bold tracking-tight text-slate-200">
            {project.visual}
          </div>

          <div className="mt-2 h-1 w-2/3 rounded bg-cyan-400/50" />
          <div className="mt-2 h-1 w-1/2 rounded bg-slate-700" />
        </div>

        <div className="absolute right-4 top-4 font-mono text-[9px] text-slate-600">
          {project.number}
        </div>
      </div>

      <div className="p-6">
        <h3 className="text-lg font-bold">{project.title}</h3>

        <p className="mt-3 min-h-20 text-xs leading-6 text-slate-500">
          {project.description}
        </p>

        <div className="mt-5 flex flex-wrap gap-1.5">
          {project.stack.map((item) => (
            <span
              key={item}
              className="rounded border border-cyan-400/15 bg-cyan-400/4 px-2 py-1 font-mono text-[8px] text-cyan-300/80"
            >
              {item}
            </span>
          ))}
        </div>

        <div className="mt-6 inline-flex items-center gap-1 text-[10px] font-bold tracking-wider text-slate-300 transition group-hover:text-cyan-300">
          VIEW PROJECT
          <ChevronRight size={13} />
        </div>
      </div>
    </Link>
  );
}

export default ProjectCard;