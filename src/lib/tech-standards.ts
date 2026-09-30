import { content } from './content';

export const TECH_STANDARDS = content.techStandards;

export type TechStandardId = (typeof TECH_STANDARDS)[number]['id'];
