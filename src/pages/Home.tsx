import Hero from "../components/sections/Hero";
import Works from "../components/sections/Works";
import BuildLog from "../components/sections/BuildLog";
import About from "../components/sections/About";
import Contact from "../components/sections/Contact";

function Home({ scrollTo }: { scrollTo: (id: string) => void }) {
  return (
    <main>
      <Hero scrollTo={scrollTo} />
      <Works />
      <BuildLog />
      <About />
      <Contact />
    </main>
  );
}

export default Home;