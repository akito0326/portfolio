import { useEffect, useState } from "react";
import { Menu, X } from "lucide-react";
import { useLocation } from "react-router-dom";
import type { NavigationItem } from "../../types/content";

type HeaderProps = {
  menuOpen: boolean;
  setMenuOpen: (value: boolean) => void;
  scrollTo: (id: string) => void;
  navigation: NavigationItem[];
};

function Header({
  menuOpen,
  setMenuOpen,
  scrollTo,
  navigation,
}: HeaderProps) {
  const { pathname } = useLocation();
  const [activeSection, setActiveSection] = useState<string | null>(null);

  useEffect(() => {
    if (pathname !== "/") {
      setActiveSection(null);
      return;
    }

    const sections = navigation.flatMap((item) => {
      const element = document.getElementById(item.target);
      return element ? [{ target: item.target, element }] : [];
    });
    const intersectingTargets = new Set<string>();

    const updateActiveSection = () => {
      const lastSection = navigation[navigation.length - 1];
      if (
        lastSection &&
        window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2
      ) {
        setActiveSection(lastSection.target);
        return;
      }

      const center = window.innerHeight / 2;
      let nearestTarget: string | null = null;
      let nearestDistance = Number.POSITIVE_INFINITY;

      for (const section of sections) {
        if (!intersectingTargets.has(section.target)) continue;

        const bounds = section.element.getBoundingClientRect();
        const distance =
          bounds.top <= center && bounds.bottom >= center
            ? 0
            : bounds.top > center
              ? bounds.top - center
              : center - bounds.bottom;

        if (distance < nearestDistance) {
          nearestTarget = section.target;
          nearestDistance = distance;
        }
      }

      setActiveSection(nearestTarget);
    };

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          const target = (entry.target as HTMLElement).id;
          if (entry.isIntersecting) {
            intersectingTargets.add(target);
          } else {
            intersectingTargets.delete(target);
          }
        }

        updateActiveSection();
      },
      { rootMargin: "-45% 0px -45% 0px" },
    );

    for (const section of sections) {
      observer.observe(section.element);
    }

    const handleScroll = () => {
      const lastSection = navigation[navigation.length - 1];
      if (
        lastSection &&
        window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2
      ) {
        setActiveSection(lastSection.target);
      }
    };

    window.addEventListener("scroll", handleScroll, { passive: true });
    updateActiveSection();

    return () => {
      observer.disconnect();
      window.removeEventListener("scroll", handleScroll);
    };
  }, [navigation, pathname]);

  return (
    <header className="fixed inset-x-0 top-0 z-50 border-b border-white/6 bg-[#080d18]/80 backdrop-blur-xl">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 lg:px-8">

        {/* Logo */}
        <button
          onClick={() => scrollTo("top")}
          className="font-mono text-sm font-bold tracking-tight"
        >
          AKITO <span className="text-cyan-400">// DEV</span>
        </button>

        {/* Desktop Navigation */}
        <nav className="hidden items-center gap-8 text-xs font-medium text-slate-400 md:flex">
          {navigation.map((item) => (
            <button
              key={item.target}
              onClick={() => scrollTo(item.target)}
              className={`transition ${activeSection === item.target ? "text-cyan-300" : "hover:text-cyan-300"}`}
            >
              <span className="mr-1 font-mono text-cyan-400">
                {item.number}
              </span>

              {item.label}
            </button>
          ))}
        </nav>

        {/* Mobile Menu Button */}
        <button
          className="rounded-md border border-white/10 p-2 text-slate-300 md:hidden"
          onClick={() => setMenuOpen(!menuOpen)}
          aria-label="menu"
          aria-expanded={menuOpen}
        >
          {menuOpen ? <X size={18} /> : <Menu size={18} />}
        </button>
      </div>

      {/* Mobile Navigation */}
      {menuOpen && (
        <div className="border-t border-white/6 bg-[#080d18] px-5 py-5 md:hidden">
          <nav className="flex flex-col gap-4 text-sm text-slate-300">
            {navigation.map((item) => (
              <button
                key={item.target}
                onClick={() => scrollTo(item.target)}
                className={`text-left transition ${activeSection === item.target ? "text-cyan-300" : "hover:text-cyan-300"}`}
              >
                <span className="mr-2 font-mono text-cyan-400">
                  {item.number}
                </span>

                {item.label}
              </button>
            ))}
          </nav>
        </div>
      )}
    </header>
  );
}

export default Header;