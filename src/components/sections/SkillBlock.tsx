function SkillBlock({ title, items }: { title: string; items: string[] }) {
  return (
    <div>
      <div className="mb-3 font-mono text-[10px] uppercase tracking-[0.16em] text-cyan-400">{title}</div>
      <div className="space-y-1 text-xs text-slate-500">
        {items.map((item) => <div key={item}>— {item}</div>)}
      </div>
    </div>
  );
}

export default SkillBlock