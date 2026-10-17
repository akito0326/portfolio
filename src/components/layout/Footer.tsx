function Footer({ scrollTo }: { scrollTo: (id: string) => void }) {
  return (
    <footer className="theme-border border-t">
      <div className="theme-text-muted mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6 text-xs font-mono sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <div>AKITO'S BASE // PORTFOLIO</div>
        <button onClick={() => scrollTo("top")} className="inline-flex min-h-11 items-center gap-1 py-2 text-sm transition hover:text-cyan-300">
          BACK TO TOP ↑
        </button>
      </div>
    </footer>
  );
}

export default Footer;