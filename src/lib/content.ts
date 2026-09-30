import siteContent from '../data/siteContent.json';

export type SiteContent = typeof siteContent;

export const content: SiteContent = siteContent;

export function homeLead(areas: readonly string[]): string {
	return content.pages.home.lead.replace('{areas}', areas.join(', '));
}
