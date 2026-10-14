import { ArrowUpRight, Mail } from "lucide-react";

function Contact() {
  return (
    <section id="contact" className="mx-auto max-w-3xl px-5 py-24 text-center lg:py-32">
      <div className="font-mono text-[10px] tracking-[0.18em] text-cyan-400">[04] CONTACT</div>
      <h2 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">一緒につくる。仕組みにする。</h2>
      <p className="mx-auto mt-4 max-w-xl text-xs leading-6 text-slate-500 sm:text-sm">
        Web制作、業務システム、API開発など。まずは「こういうの面倒」を聞かせてください。
      </p>

      <form className="mx-auto mt-10 grid gap-3 text-left" onSubmit={(e) => e.preventDefault()}>
        <div className="grid gap-3 sm:grid-cols-2">
          <input className="h-12 rounded border border-slate-800 bg-[#101827] px-4 text-xs outline-none transition placeholder:text-slate-600 focus:border-cyan-400/50" placeholder="NAME" />
          <input className="h-12 rounded border border-slate-800 bg-[#101827] px-4 text-xs outline-none transition placeholder:text-slate-600 focus:border-cyan-400/50" placeholder="EMAIL" type="email" />
        </div>
        <textarea className="min-h-36 resize-y rounded border border-slate-800 bg-[#101827] p-4 text-xs outline-none transition placeholder:text-slate-600 focus:border-cyan-400/50" placeholder="MESSAGE" />
        <button className="mt-2 inline-flex h-12 items-center justify-center gap-2 rounded bg-cyan-400 px-6 text-xs font-bold text-slate-950 transition hover:bg-cyan-300">
          SEND MESSAGE <ArrowUpRight size={14} />
        </button>
      </form>

      <div className="mt-10 flex justify-center gap-6 text-slate-500">
        <a href="#" aria-label="GitHub" className="font-mono text-xs transition hover:text-cyan-300">GitHub</a>
        <a href="#" aria-label="X" className="font-mono text-xs transition hover:text-cyan-300">X</a>
        <a href="mailto:" aria-label="Email" className="transition hover:text-cyan-300"><Mail size={17} /></a>
      </div>
    </section>
  );
}

export default Contact