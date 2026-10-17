import { ArrowUpRight } from "lucide-react";

function Hero({ scrollTo }: { scrollTo: (id: string) => void }) {
  return (
    <section id="top" className="theme-border relative flex min-h-180 items-center justify-center overflow-hidden border-b pt-16">
      <div className="absolute inset-0 opacity-30 bg-[linear-gradient(rgba(34,211,238,.12)_1px,transparent_1px),linear-gradient(90deg,rgba(34,211,238,.12)_1px,transparent_1px)] [bg-size:80px_80px] mask-[linear-gradient(to_bottom,black,transparent_85%)]" />
      <div className="absolute left-1/2 top-1/2 h-130 w-130 -translate-x-1/2 -translate-y-1/2 rounded-full bg-cyan-500/10 blur-[120px]" />

      <div className="relative z-10 mx-auto w-full max-w-5xl px-4 text-center lg:px-8">
        <div className="theme-text-muted mb-7 inline-flex items-center gap-2 font-mono text-xs tracking-[0.22em]">
          <span className="text-cyan-400">[BUILD_MODE]</span>
          <span>CREATE / SYSTEMIZE / REPEAT</span>
        </div>

        <h1 className="mx-auto max-w-4xl text-3xl font-black leading-tight tracking-tight md:text-5xl">
          MAKE IT SIMPLE
        </h1>

        <p className="theme-text-secondary mx-auto mt-7 max-w-2xl text-base leading-relaxed tracking-wide">
          面倒なことを、仕組みにする。
        </p>

        <div className="mt-8 flex flex-wrap justify-center gap-4">
          <button
            onClick={() => scrollTo("works")}
            className="group inline-flex min-h-11 items-center gap-2 rounded-sm bg-cyan-400 px-6 py-3 text-base font-bold leading-tight text-slate-950 shadow-[0_0_35px_rgba(34,211,238,.16)] transition hover:bg-cyan-300"
          >
            WORKSを見る
            <ArrowUpRight size={15} className="transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
          </button>
          <button
            onClick={() => scrollTo("about")}
            className="theme-border-strong theme-surface-muted theme-text-primary min-h-11 rounded-sm border px-6 py-3 text-base font-bold leading-tight transition hover:border-cyan-400/50"
          >
            ABOUT
          </button>
        </div>

        <div className="theme-surface theme-text-muted mx-auto mt-12 flex max-w-2xl flex-col items-start rounded border border-cyan-400/10 px-4 py-4 text-left font-mono text-xs shadow-2xl backdrop-blur sm:flex-row sm:items-center sm:justify-between">
          <span><span className="text-cyan-400">$</span> whoami → akito</span>
          <span className="hidden text-cyan-400/70 sm:block">STATUS // BUILDING</span>
          <span className="hidden sm:block">React / TS / PHP / AWS</span>
        </div>
      </div>
    </section>
  );
}

export default Hero;