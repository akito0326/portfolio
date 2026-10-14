function Footer({ scrollTo }: { scrollTo: (id: string) => void }) {
  return (
    <footer className="border-t border-white/6">
      <div className="mx-auto flex max-w-6xl flex-col gap-5 px-5 py-7 text-[9px] font-mono text-slate-600 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <div>AKITO'S BASE // PORTFOLIO</div>
        <button onClick={() => scrollTo("top")} className="inline-flex items-center gap-1 transition hover:text-cyan-300">
          BACK TO TOP ↑
        </button>
      </div>
    </footer>
  );
}

export default Footer;