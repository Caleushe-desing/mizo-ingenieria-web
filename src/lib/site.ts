import { content } from './content';

export const SITE = {
	name: content.site.name,
	legalName: content.site.name,
	url: 'https://mizo.cl',
	title: content.site.title,
	description: content.site.description,
	email: content.site.email,
	phone: '+56994390870',
	phoneDisplay: content.site.phoneDisplay,
	whatsapp: '56994390870',
	whatsappMessage: content.site.whatsappMessage,
	address: content.site.address,
	hours: content.site.hours,
	locale: 'es_CL',
	language: 'es-CL',
	ogImage: '/mizo-social-preview.jpg.png',
	logo: '/mizo-logo.png',
	logoFooter: '/mizo-logo-footer.png',
	areas: content.site.areas,
} as const;

export const NAV = content.nav;

export const BRANDS = [
	{ name: 'Sony', slug: 'sony' },
	{ name: 'Sennheiser', slug: 'sennheiser' },
	{ name: 'JBL', slug: 'jbl' },
	{ name: 'Bose', slug: 'bose' },
	{ name: 'Shure', slug: 'shure' },
	{ name: 'Epson', slug: 'epson' },
	{ name: 'BenQ', slug: 'benq' },
] as const;

export function whatsappUrl(message = SITE.whatsappMessage): string {
	return `https://wa.me/${SITE.whatsapp}?text=${encodeURIComponent(message)}`;
}

export function telUrl(): string {
	return `tel:${SITE.phone}`;
}

export function mailUrl(): string {
	return `mailto:${SITE.email}`;
}
