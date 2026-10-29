// Отзывы хранятся в public/data/reviews.json (редактируются из админки /admin/).
// TODO: сейчас ЧЕРНОВЫЕ отзывы, имена и фото вымышлены. Заменить реальными (с согласия авторов).
import data from '../../public/data/reviews.json' with { type: 'json' };
export const reviews = data;
