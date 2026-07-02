export const SITE = {
	name: 'Mizo Ingeniería',
	legalName: 'Mizo',
	url: 'https://mizo.cl',
	description:
		'Diseño e instalación de sistemas de sonido, videoproyección, instalaciones eléctricas y salas audiovisuales para colegios, iglesias, auditorios, restaurantes y empresas en Chile.',
	email: 'ventas@mizo.cl',
	phone: '+56994390870',
	phoneDisplay: '+56 9 9439 0870',
	logo: 'https://mizo.cl/mizo-logo.svg',
	defaultOgImage:
		'https://images.unsplash.com/photo-1507901747481-84a4f64fda6d?auto=format&fit=crop&w=1200&h=630&q=80',
	locale: 'es_CL',
	language: 'es-CL',
	areas: ['Frutillar', 'Santiago', 'Chile'],
	sameAs: [] as string[],
} as const;

export type JsonLd = Record<string, unknown>;

export function absoluteUrl(path: string, site = SITE.url): string {
	return new URL(path, site).href;
}

export function organizationId(site = SITE.url): string {
	return `${site}/#organization`;
}

export function websiteId(site = SITE.url): string {
	return `${site}/#website`;
}

export function organizationSchema(site = SITE.url): JsonLd {
	return {
		'@type': 'Organization',
		'@id': organizationId(site),
		name: SITE.name,
		legalName: SITE.legalName,
		url: site,
		logo: SITE.logo,
		image: SITE.logo,
		description: SITE.description,
		email: SITE.email,
		telephone: SITE.phone,
		areaServed: {
			'@type': 'Country',
			name: 'Chile',
		},
		sameAs: SITE.sameAs,
	};
}

export function localBusinessSchema(site = SITE.url): JsonLd {
	return {
		'@type': 'LocalBusiness',
		'@id': `${site}/#localbusiness`,
		name: SITE.name,
		url: site,
		image: SITE.logo,
		logo: SITE.logo,
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
		areaServed: ['Frutillar', 'Santiago', 'Chile'].map((name) => ({
			'@type': 'AdministrativeArea',
			name,
		})),
	};
}

export function websiteSchema(site = SITE.url): JsonLd {
	return {
		'@type': 'WebSite',
		'@id': websiteId(site),
		url: site,
		name: SITE.name,
		description: SITE.description,
		inLanguage: SITE.language,
		publisher: {
			'@id': organizationId(site),
		},
		potentialAction: {
			'@type': 'SearchAction',
			target: {
				'@type': 'EntryPoint',
				urlTemplate: `${site}/productos?q={search_term_string}`,
			},
			'query-input': 'required name=search_term_string',
		},
	};
}

export function webPageSchema(input: {
	name: string;
	description: string;
	url: string;
}): JsonLd {
	return {
		'@type': 'WebPage',
		'@id': `${input.url}#webpage`,
		url: input.url,
		name: input.name,
		description: input.description,
		isPartOf: {
			'@id': websiteId(),
		},
		about: {
			'@id': organizationId(),
		},
		inLanguage: SITE.language,
	};
}

export function breadcrumbSchema(
	items: Array<{ name: string; url: string }>,
): JsonLd {
	return {
		'@type': 'BreadcrumbList',
		itemListElement: items.map((item, index) => ({
			'@type': 'ListItem',
			position: index + 1,
			name: item.name,
			item: item.url,
		})),
	};
}

export function productSchema(input: {
	name: string;
	description: string;
	url: string;
	image?: string;
	brand?: string;
	sku?: string;
	category?: string;
}): JsonLd {
	const schema: JsonLd = {
		'@type': 'Product',
		'@id': `${input.url}#product`,
		name: input.name,
		description: input.description,
		url: input.url,
		image: input.image ? [input.image] : [SITE.logo],
		brand: {
			'@type': 'Brand',
			name: input.brand || SITE.name,
		},
		manufacturer: {
			'@type': 'Organization',
			name: input.brand || SITE.name,
		},
		offers: {
			'@type': 'Offer',
			url: input.url,
			availability: 'https://schema.org/InStock',
			priceCurrency: 'CLP',
			seller: {
				'@id': organizationId(),
			},
		},
	};

	if (input.sku) schema.sku = input.sku;
	if (input.category) schema.category = input.category;

	return schema;
}

export function itemListSchema(input: {
	name: string;
	description: string;
	url: string;
	items: Array<{ name: string; url: string }>,
}): JsonLd {
	return {
		'@type': 'ItemList',
		name: input.name,
		description: input.description,
		url: input.url,
		numberOfItems: input.items.length,
		itemListElement: input.items.slice(0, 50).map((item, index) => ({
			'@type': 'ListItem',
			position: index + 1,
			name: item.name,
			url: item.url,
		})),
	};
}

export function serviceSchema(input: {
	name: string;
	description: string;
	url: string;
	areaServed?: string;
}): JsonLd {
	return {
		'@type': 'Service',
		name: input.name,
		description: input.description,
		url: input.url,
		provider: {
			'@id': organizationId(),
		},
		areaServed: input.areaServed || 'Chile',
	};
}

export function homePageSchema(site = SITE.url): JsonLd[] {
	return [
		organizationSchema(site),
		localBusinessSchema(site),
		websiteSchema(site),
		webPageSchema({
			name: 'Mizo | Sonido, videoproyección e instalaciones eléctricas en Chile',
			description: SITE.description,
			url: site,
		}),
	];
}

export function faqPageSchema(
	items: Array<{ question?: string; answer?: string; q?: string; a?: string }>,
): JsonLd {
	return {
		'@type': 'FAQPage',
		mainEntity: items.map((item) => ({
			'@type': 'Question',
			name: item.question ?? item.q ?? '',
			acceptedAnswer: {
				'@type': 'Answer',
				text: item.answer ?? item.a ?? '',
			},
		})),
	};
}

export function contactPageSchema(site = SITE.url): JsonLd[] {
	const url = absoluteUrl('/contacto', site);
	return [
		localBusinessSchema(site),
		{
			'@type': 'ContactPage',
			'@id': `${url}#contactpage`,
			url,
			name: 'Contacto | Mizo',
			description: 'Contáctanos para cotizaciones de instalación de sonido, audiovisual y equipos en Chile.',
			mainEntity: {
				'@id': `${site}/#localbusiness`,
			},
		},
	];
}
