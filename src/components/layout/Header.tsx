import { useEffect, useState } from "react";
import { Menu, Moon, Sun, X } from "lucide-react";
import { useLocation } from "react-router-dom";
import type { NavigationItem } from "../../types/content";

type HeaderProps = {
  menuOpen: boolean;
  setMenuOpen: (value: boolean) => void;
  scrollTo: (id: string) => void;
  navigation: NavigationItem[];
  theme: "light" | "dark";
  toggleTheme: () => void;
};

function Header({
  menuOpen,
  setMenuOpen,
  scrollTo,
  navigation,
  theme,
  toggleTheme,
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
    <header className="theme-header theme-border fixed inset-x-0 top-0 z-50 border-b backdrop-blur-xl">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-8">

        {/* Logo */}
        <button
          onClick={() => scrollTo("top")}
          className="min-h-11 font-mono text-base font-bold tracking-tight"
        >
          AKITO <span className="text-cyan-400">// DEV</span>
        </button>

        {/* Desktop Navigation */}
        <nav className="theme-text-secondary hidden items-center gap-8 text-base font-medium md:flex">
          {navigation.map((item) => (
            <button
              key={item.target}
              onClick={() => scrollTo(item.target)}
              className={`min-h-11 py-2 transition ${activeSection === item.target ? "text-cyan-300" : "hover:text-cyan-300"}`}
            >
              <span className="mr-1 font-mono text-cyan-400">
                {item.number}
              </span>

              {item.label}
            </button>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <button
            className="theme-border-strong theme-text-secondary inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border p-2 transition hover:text-cyan-500"
            onClick={toggleTheme}
            aria-label={theme === "light" ? "ダークモードに切り替え" : "ライトモードに切り替え"}
            aria-pressed={theme === "dark"}
            title={theme === "light" ? "ダークモードに切り替え" : "ライトモードに切り替え"}
          >
            {theme === "light" ? <Moon size={18} /> : <Sun size={18} />}
          </button>

          <button
            className="theme-border-strong theme-text-secondary inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border p-2 md:hidden"
            onClick={() => setMenuOpen(!menuOpen)}
            aria-label="メニュー"
            aria-expanded={menuOpen}
          >
            {menuOpen ? <X size={18} /> : <Menu size={18} />}
          </button>
        </div>
      </div>

      {/* Mobile Navigation */}
      {menuOpen && (
        <div className="theme-border theme-surface border-t px-4 py-4 md:hidden">
          <nav className="theme-text-secondary flex flex-col gap-4 text-base">
            {navigation.map((item) => (
              <button
                key={item.target}
                onClick={() => scrollTo(item.target)}
                className={`min-h-11 py-2 text-left transition ${activeSection === item.target ? "text-cyan-300" : "hover:text-cyan-300"}`}
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