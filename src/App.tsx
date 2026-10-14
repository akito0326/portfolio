import { useState } from "react";
import { BrowserRouter, Routes, Route } from "react-router-dom";

import ScrollToTop from "./components/layout/ScrollToTop";
import Header from "./components/layout/Header";
import Footer from "./components/layout/Footer";
import Home from "./pages/Home";
import ProjectDetail from "./pages/ProjectDetail";
import { navigation } from "./data";

function App() {
  const [menuOpen, setMenuOpen] = useState(false);

  const scrollTo = (id: string) => {
    document.getElementById(id)?.scrollIntoView({
      behavior: "smooth",
    });

    setMenuOpen(false);
  };

  return (
    <BrowserRouter>
      <ScrollToTop />
      <div className="min-h-screen overflow-x-hidden bg-[#080d18] text-slate-100">
        <Header
          menuOpen={menuOpen}
          setMenuOpen={setMenuOpen}
          scrollTo={scrollTo}
          navigation={navigation}
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