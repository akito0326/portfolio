import { ArrowUpRight, Mail } from "lucide-react";

function Contact() {
  return (
    <section id="contact" className="mx-auto max-w-3xl px-4 py-24 text-center lg:py-32">
      <div className="font-mono text-xs tracking-[0.18em] text-cyan-400">[04] CONTACT</div>
      <h2 className="mt-4 text-2xl font-black leading-snug tracking-tight md:text-3xl">一緒につくる。仕組みにする。</h2>
      <p className="theme-text-secondary mx-auto mt-4 max-w-xl text-base leading-relaxed tracking-wide">
        Web制作、業務システム、API開発など。まずは「こういうの面倒」を聞かせてください。
      </p>

      <form className="mx-auto mt-12 grid max-w-xl gap-4 text-left" onSubmit={(e) => e.preventDefault()}>
        <div className="grid gap-4 sm:grid-cols-2">
          <input className="theme-border-strong theme-surface theme-text-primary h-12 rounded border px-4 text-base outline-none transition focus:border-cyan-400/50" placeholder="NAME" />
          <input className="theme-border-strong theme-surface theme-text-primary h-12 rounded border px-4 text-base outline-none transition focus:border-cyan-400/50" placeholder="EMAIL" type="email" />
        </div>
        <textarea className="theme-border-strong theme-surface theme-text-primary min-h-36 resize-y rounded border p-4 text-base outline-none transition focus:border-cyan-400/50" placeholder="MESSAGE" />
        <button className="mt-2 inline-flex min-h-11 items-center justify-center gap-2 rounded bg-cyan-400 px-6 py-3 text-base font-bold leading-tight text-slate-950 transition hover:bg-cyan-300">
          SEND MESSAGE <ArrowUpRight size={14} />
        </button>
      </form>

      <div className="theme-text-muted mt-12 flex justify-center gap-6">
        <a href="#" aria-label="GitHub" className="inline-flex min-h-11 items-center py-2 font-mono text-sm transition hover:text-cyan-300">GitHub</a>
        <a href="#" aria-label="X" className="inline-flex min-h-11 items-center py-2 font-mono text-sm transition hover:text-cyan-300">X</a>
        <a href="mailto:" aria-label="Email" className="inline-flex min-h-11 min-w-11 items-center justify-center transition hover:text-cyan-300"><Mail size={17} /></a>
      </div>
    </section>
  );
}

export default Contact