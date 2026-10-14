function SectionHeading({ number, eyebrow, title, description }: {
  number: string;
  eyebrow: string;
  title: string;
  description: string;
}) {
  return (
    <div className="mb-12">
      <div className="mb-2 font-mono text-[10px] tracking-[0.18em] text-cyan-400">
        [{number}] {eyebrow}
      </div>
      <h2 className="text-3xl font-black tracking-tight sm:text-4xl">{title}</h2>
      <p className="mt-3 max-w-2xl text-xs leading-6 text-slate-500 sm:text-sm">{description}</p>
    </div>
  );
}

export default SectionHeading