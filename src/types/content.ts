export type ProjectLink = {
  github?: string;
  demo?: string;
};

export type Project = {
  number: string;
  slug: string;
  title: string;
  description: string;
  stack: string[];
  accent: string;
  visual: string;

  overview?: string;
  problem?: string;
  solution?: string;
  features?: string[];
  architecture?: string[];
  links?: ProjectLink;
};

export type BuildLog = {
  date: string;
  title: string;
  tag: string;
};

export type NavigationItem = {
  number: string;
  label: string;
  target: string;
};

export type SkillGroup = {
  title: string;
  items: string[];
};
