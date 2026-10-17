import { useEffect, useState } from "react";
import { BrowserRouter, Routes, Route } from "react-router-dom";

import ScrollToTop from "./components/layout/ScrollToTop";
import Header from "./components/layout/Header";
import Footer from "./components/layout/Footer";
import Home from "./pages/Home";
import ProjectDetail from "./pages/ProjectDetail";
import { navigation } from "./data";

type Theme = "light" | "dark";
const themeStorageKey = "portfolio-theme";

function App() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [theme, setTheme] = useState<Theme>(() => {
    const savedTheme = localStorage.getItem(themeStorageKey);
    return savedTheme === "dark" ? "dark" : "light";
  });

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem(themeStorageKey, theme);
  }, [theme]);

  const scrollTo = (id: string) => {
    document.getElementById(id)?.scrollIntoView({
      behavior: "smooth",
    });

    setMenuOpen(false);
  };

  return (
    <BrowserRouter>
      <ScrollToTop />
      <div className="theme-bg theme-text-primary min-h-screen overflow-x-hidden">
        <Header
          menuOpen={menuOpen}
          setMenuOpen={setMenuOpen}
          scrollTo={scrollTo}
          navigation={navigation}
          theme={theme}
          toggleTheme={() => setTheme((currentTheme) => currentTheme === "light" ? "dark" : "light")}
        />

        <Routes>
          <Route path="/" element={<Home scrollTo={scrollTo} />} />
          <Route path="/works/:id" element={<ProjectDetail />} />
        </Routes>

        <Footer scrollTo={scrollTo} />
      </div>
    </BrowserRouter>
  );
}

export default App;