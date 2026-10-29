import { site, siteUrl } from './site.config.mjs';

// schema.org LocalBusiness — подключать на главной и в контактах
export const localBusiness = {
  '@context': 'https://schema.org',
  '@type': 'HomeAndConstructionBusiness',
  name: site.name,
  description: site.tagline,
  url: siteUrl,
  telephone: site.phone.href,
  email: site.email,
  image: `${siteUrl}/og-default.jpg`,
  address: {
    '@type': 'PostalAddress',
    streetAddress: site.address.street,
    addressLocality: site.address.city,
    addressRegion: site.address.region,
    postalCode: site.address.postalCode,
    addressCountry: site.address.country,
  },
  geo: { '@type': 'GeoCoordinates', latitude: site.geo.lat, longitude: site.geo.lng },
  openingHours: 'Mo-Su 09:00-20:00', // TODO: синхронизировать с site.hours
  areaServed: 'Москва и Московская область',
};
