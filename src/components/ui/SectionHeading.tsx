function SectionHeading({ number, eyebrow, title, description }: {
  number: string;
  eyebrow: string;
  title: string;
  description: string;
}) {
  return (
    <div className="mb-12">
      <div className="mb-2 font-mono text-xs tracking-[0.18em] text-cyan-400">
        [{number}] {eyebrow}
      </div>
      <h2 className="text-2xl font-black leading-snug tracking-tight md:text-3xl">{title}</h2>
      <p className="theme-text-secondary mt-4 max-w-2xl text-base leading-relaxed tracking-wide">{description}</p>
    </div>
  );
}

export default SectionHeading