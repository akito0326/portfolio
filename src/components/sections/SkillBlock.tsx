function SkillBlock({ title, items }: { title: string; items: string[] }) {
  return (
    <div>
      <div className="mb-4 font-mono text-xl font-semibold uppercase tracking-[0.16em] text-cyan-400">{title}</div>
      <div className="theme-text-secondary max-w-prose space-y-1 text-base leading-relaxed tracking-wide">
        {items.map((item) => <div key={item}>— {item}</div>)}
      </div>
    </div>
  );
}

export default SkillBlock