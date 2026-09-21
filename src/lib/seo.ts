import { SITE } from './site';

export type JsonLd = Record<string, unknown>;

export function absoluteUrl(path = '/', site = SITE.url): string {
	return new URL(path, site).href;
}

export function organizationSchema(site = SITE.url): JsonLd {
	return {
		'@type': 'Organization',
		'@id': `${site}/#organization`,
		name: SITE.name,
		legalName: SITE.legalName,
		url: site,
		logo: absoluteUrl(SITE.logo, site),
		image: absoluteUrl(SITE.ogImage, site),
		description: SITE.description,
		email: SITE.email,
		telephone: SITE.phone,
		areaServed: {
			'@type': 'Country',
			name: 'Chile',
		},
	};
}

export function localBusinessSchema(site = SITE.url): JsonLd {
	return {
		'@type': 'LocalBusiness',
		'@id': `${site}/#localbusiness`,
		name: SITE.name,
		url: site,
		image: absoluteUrl(SITE.logo, site),
		logo: absoluteUrl(SITE.logo, site),
		description: SITE.description,
		email: SITE.email,
		telephone: SITE.phone,
		priceRange: '$$',
		address: {
			'@type': 'PostalAddress',
			addressLocality: 'Frutillar',
			addressRegion: 'Los Lagos',
			addressCountry: 'CL',
		},
		areaServed: SITE.areas.map((name) => ({
			'@type': 'AdministrativeArea',
			name,
		})),
		openingHoursSpecification: {
			'@type': 'OpeningHoursSpecification',
			dayOfWeek: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
			opens: '09:00',
			closes: '18:00',
		},
	};
}

export function websiteSchema(site = SITE.url): JsonLd {
	return {
		'@type': 'WebSite',
		'@id': `${site}/#website`,
		url: site,
		name: SITE.name,
		description: SITE.description,
		inLanguage: SITE.language,
		publisher: { '@id': `${site}/#organization` },
	};
}

export function webPageSchema(opts: { name: string; description: string; url: string }): JsonLd {
	return {
		'@type': 'WebPage',
		name: opts.name,
		description: opts.description,
		url: opts.url,
		isPartOf: { '@id': `${SITE.url}/#website` },
		about: { '@id': `${SITE.url}/#organization` },
	};
}

export function serviceSchema(opts: { name: string; description: string; url: string }): JsonLd {
	return {
		'@type': 'Service',
		name: opts.name,
		description: opts.description,
		url: opts.url,
		provider: { '@id': `${SITE.url}/#organization` },
		areaServed: { '@type': 'Country', name: 'Chile' },
	};
}

export function graphSchema(nodes: JsonLd[]): JsonLd {
	return {
		'@context': 'https://schema.org',
		'@graph': nodes,
	};
}
