import assert from "node:assert/strict";
import test from "node:test";
import { serializeBuildLog } from "../../lib/api/build-logs.ts";
import { serializeProject } from "../../lib/api/projects.ts";

test("project response keeps the PHP model field names and optional fields", () => {
  const response = serializeProject({
    id: "17",
    display_order: 3,
    slug: "sample-project",
    title: "Sample",
    description: "Description",
    accent: "ACCENT",
    visual: "VISUAL",
    overview: "Overview",
    problem: null,
    solution: "Solution",
    features: ["Feature"],
    architecture: ["Architecture"],
    stack: ["TypeScript", "PostgreSQL"],
    github_url: "",
    demo_url: "/demo",
    created_at: "2026-10-14 00:00:00+00",
    updated_at: "2026-10-14 00:00:00+00",
  });

  assert.deepEqual(response, {
    id: 17,
    number: "03",
    slug: "sample-project",
    title: "Sample",
    description: "Description",
    stack: ["TypeScript", "PostgreSQL"],
    accent: "ACCENT",
    visual: "VISUAL",
    overview: "Overview",
    solution: "Solution",
    features: ["Feature"],
    architecture: ["Architecture"],
    links: { github: "", demo: "/demo" },
    created_at: "2026-10-14 00:00:00+00",
    updated_at: "2026-10-14 00:00:00+00",
  });
});

test("build-log response retains public and management fields and formats its date", () => {
  assert.deepEqual(
    serializeBuildLog({
      id: "4",
      title: "Build",
      tag: "Backend",
      logged_at: "2026-09-01",
      sort_order: 2,
      is_public: true,
    }),
    {
      id: 4,
      date: "2026.09",
      title: "Build",
      tag: "Backend",
      logged_at: "2026-09-01",
      sort_order: 2,
      is_public: true,
    },
  );
});
