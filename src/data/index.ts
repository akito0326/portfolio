import projectsJson from "./projects.json";
import buildLogsJson from "./buildLogs.json";
import navigationJson from "./navigation.json";
import skillsJson from "./skills.json";

import type {
  Project,
  BuildLog,
  NavigationItem,
  SkillGroup,
} from "../types/content";

export const projects = projectsJson as Project[];
export const buildLogs = buildLogsJson as BuildLog[];
export const navigation = navigationJson as NavigationItem[];
export const skills = skillsJson as SkillGroup[];