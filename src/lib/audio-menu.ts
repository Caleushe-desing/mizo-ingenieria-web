import { industryPath } from './industries';
import { INDUSTRY_PAGES } from './industry-pages';
import { SOUND_LANDINGS, landingPath } from './landings';

export type AudioMenuLink = {
	href: string;
	nav: string;
	lead: string;
};

export function audioMenuLinks(currentHref: string): AudioMenuLink[] {
	const links: AudioMenuLink[] = [
		...SOUND_LANDINGS.map((landing) => ({
			href: landingPath(landing.slug),
			nav: landing.slug === 'parlantes-para-iglesias' ? 'Iglesias' : landing.nav,
			lead: landing.lead,
		})),
		...INDUSTRY_PAGES.map((page) => ({
			href: industryPath(page.slug),
			nav: page.nav,
			lead: page.lead,
		})),
	];
	const current = currentHref.replace(/\/$/, '') || '/';
	return links.filter((item) => item.href !== current);
}
