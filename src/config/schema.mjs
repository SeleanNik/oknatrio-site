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
  areaServed: [{ '@type': 'City', name: 'Москва' }, { '@type': 'AdministrativeArea', name: 'Московская область' }],
  logo: `${siteUrl}/favicon.svg`,
  alternateName: ['Трио', 'Окна Трио', 'Трио Инжиниринг Москва'],
  knowsAbout: ['алюминиевые окна', 'пластиковые окна ПВХ', 'Schuco', 'Alutech', 'VEKA', 'остекление балконов и лоджий', 'фасадное остекление', 'зимние сады'],
  hasMap: `https://yandex.ru/maps/?text=${encodeURIComponent(`${site.address.city}, ${site.address.street}`)}`,
  contactPoint: { '@type': 'ContactPoint', telephone: site.phone.href, contactType: 'customer service', areaServed: 'RU', availableLanguage: 'Russian' },
};

// Название сайта для поисковиков (Яндекс показывает его в выдаче)
export const webSite = {
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: site.name,
  alternateName: ['Трио', 'oknatrio.ru'],
  url: siteUrl,
  inLanguage: 'ru-RU',
  publisher: { '@type': 'Organization', name: site.name, url: siteUrl },
};
