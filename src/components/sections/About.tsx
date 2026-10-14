import { Terminal } from "lucide-react";
import SectionHeading from "../ui/SectionHeading";
import SkillBlock from "./SkillBlock";
import { skills } from "../../data";

function About() {
  return (
    <section id="about" className="border-b border-white/6 bg-[#111a2b]">
      <div className="mx-auto grid max-w-6xl gap-12 px-5 py-24 lg:grid-cols-[1.3fr_.7fr] lg:px-8 lg:py-32">
        <div>
          <SectionHeading
            number="03"
            eyebrow="ABOUT"
            title="AKITO"
            description="Webを中心に、「面倒なことを仕組みにする」ことを考えています。"
          />

          <div className="grid gap-8 sm:grid-cols-2">
            {skills.map((skill) => (
              <SkillBlock key={skill.title} title={skill.title} items={skill.items} />
            ))}
          </div>
        </div>

        <div className="relative flex min-h-90 items-center justify-center overflow-hidden rounded-xl border border-cyan-400/20 bg-[#080d18]">
          <div className="absolute inset-0 opacity-20 bg-[linear-gradient(rgba(34,211,238,.18)_1px,transparent_1px),linear-gradient(90deg,rgba(34,211,238,.18)_1px,transparent_1px)] [bg-size:40px_40px]" />
          <div className="relative text-center">
            <Terminal className="mx-auto mb-5 text-cyan-400" size={38} strokeWidth={1.5} />
            <div className="font-mono text-3xl font-bold tracking-tight">AKITO<span className="text-cyan-400">.</span></div>
            <div className="mt-2 font-mono text-[9px] tracking-[0.3em] text-slate-600">BUILD / AUTOMATE / IMPROVE</div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default About