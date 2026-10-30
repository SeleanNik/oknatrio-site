<?php
// Описание редактируемого контента страниц и универсальные форма/проверка по этому описанию.
// Типы полей: text, textarea, lines, link, image, gallery, pdf, tags, id, list (один уровень вложенности).
// Списки показываются компактными карточками; редактирование открывается в окне.
declare(strict_types=1);

const TAG_OPTIONS = ['apartment' => 'Квартира', 'cottage' => 'Коттедж', 'balcony' => 'Балкон', 'aluminum' => 'Алюминий', 'pvc' => 'ПВХ'];

const PAGE_URLS = [
    'home' => '/',
    'alyuminievye' => '/okna/alyuminievye/',
    'pvh' => '/okna/pvh/',
    'works' => '/nashi-raboty/',
    'tech' => '/tehnicheskaya-informaciya/',
    'contacts' => '/kontakty/',
];

function f_type_item(): array
{
    return [
        ['k' => 'id', 'type' => 'id'],
        ['k' => 'title', 'type' => 'text', 'label' => 'Название', 'max' => 80, 'req' => true],
        ['k' => 'text', 'type' => 'textarea', 'label' => 'Описание', 'max' => 200, 'rows' => 2],
        ['k' => 'images', 'type' => 'gallery', 'max' => 8, 'label' => 'Фото (несколько: на сайте они листаются. Перетащите фото, чтобы изменить порядок; первое фото — обложка)'],
        ['k' => 'points', 'type' => 'textarea', 'label' => 'Особенности (по одной в строке, лучше 2)', 'max' => 120, 'rows' => 2],
    ];
}

function f_file_item(): array
{
    return [
        ['k' => 'title', 'type' => 'text', 'label' => 'Название', 'max' => 100, 'req' => true],
        ['k' => 'pdf', 'type' => 'pdf', 'label' => 'PDF-файл'],
        ['k' => 'size', 'type' => 'text', 'label' => 'Размер (для подписи, например 12 МБ)', 'max' => 20],
    ];
}

function page_schemas(): array
{
    $razdel = function (string $label, array $typeGroups): array {
        return [
            'label' => $label,
            'groups' => array_merge(
                [['title' => 'Заголовок страницы', 'fields' => [
                    ['k' => 'h1', 'type' => 'lines', 'n' => 2, 'max' => 50, 'label' => 'Заголовок (2 строки)'],
                    ['k' => 'lead', 'type' => 'textarea', 'max' => 220, 'rows' => 3, 'label' => 'Подзаголовок'],
                ]]],
                $typeGroups,
                [
                    ['title' => 'Характеристики', 'hint' => 'Карточки с параметрами под видами.', 'fields' => [
                        ['k' => 'specs', 'type' => 'list', 'max' => 6, 'row' => 'param', 'sub' => 'value', 'label' => 'Параметры', 'add' => 'Добавить параметр', 'item' => [
                            ['k' => 'param', 'type' => 'text', 'label' => 'Название', 'max' => 30, 'req' => true],
                            ['k' => 'value', 'type' => 'text', 'label' => 'Значение', 'max' => 40],
                        ]],
                    ]],
                    ['title' => 'Вопросы и ответы', 'fields' => [
                        ['k' => 'faq', 'type' => 'list', 'max' => 12, 'row' => 'q', 'sub' => 'a', 'label' => 'Вопросы', 'add' => 'Добавить вопрос', 'item' => [
                            ['k' => 'q', 'type' => 'text', 'label' => 'Вопрос', 'max' => 150, 'req' => true],
                            ['k' => 'a', 'type' => 'textarea', 'label' => 'Ответ', 'max' => 600, 'rows' => 4],
                        ]],
                    ]],
                ]
            ),
        ];
    };

    return [
        'home' => ['label' => 'Главная', 'groups' => [
            ['title' => 'Главный экран', 'fields' => [
                ['k' => 'hero', 'type' => 'lines', 'n' => 2, 'max' => 50, 'label' => 'Заголовок (2 строки)'],
            ]],
            ['title' => 'Блок «Продукция»', 'fields' => [
                ['k' => 'prod.title', 'type' => 'text', 'max' => 40, 'label' => 'Заголовок'],
                ['k' => 'prod.text', 'type' => 'textarea', 'max' => 170, 'rows' => 2, 'label' => 'Описание (до двух строк)'],
                ['k' => 'prod.cards', 'type' => 'list', 'fixed' => true, 'row' => 'title', 'sub' => 'brand', 'thumb' => 'image', 'label' => 'Карточки (5 штук, количество не меняется)', 'item' => [
                    ['k' => 'title', 'type' => 'text', 'label' => 'Название', 'max' => 40, 'req' => true],
                    ['k' => 'brand', 'type' => 'text', 'label' => 'Бренд (необязательно: Alutech, Schuco, VEKA)', 'max' => 20],
                    ['k' => 'image', 'type' => 'image', 'label' => 'Фото'],
                    ['k' => 'href', 'type' => 'link', 'label' => 'Куда ведёт карточка', 'max' => 200],
                ]],
            ]],
            ['title' => 'Блок «Услуги»', 'fields' => [
                ['k' => 'services.title', 'type' => 'text', 'max' => 40, 'label' => 'Заголовок'],
                ['k' => 'services.items', 'type' => 'list', 'fixed' => true, 'row' => 'title', 'label' => 'Услуги (4 штуки)', 'item' => [
                    ['k' => 'title', 'type' => 'text', 'label' => 'Название', 'max' => 80, 'req' => true],
                ]],
            ]],
            ['title' => 'Блок «Наши работы»', 'hint' => 'Сами объекты редактируются на странице «Наши работы».', 'fields' => [
                ['k' => 'works.title', 'type' => 'text', 'max' => 40, 'label' => 'Заголовок'],
                ['k' => 'works.text', 'type' => 'textarea', 'max' => 170, 'rows' => 2, 'label' => 'Описание'],
            ]],
            ['title' => 'Вопросы и ответы', 'hint' => 'Блок «Частые вопросы» перед формой заявки.', 'fields' => [
                ['k' => 'faq', 'type' => 'list', 'max' => 12, 'row' => 'q', 'sub' => 'a', 'label' => 'Вопросы', 'add' => 'Добавить вопрос', 'item' => [
                    ['k' => 'q', 'type' => 'text', 'label' => 'Вопрос', 'max' => 150, 'req' => true],
                    ['k' => 'a', 'type' => 'textarea', 'label' => 'Ответ', 'max' => 600, 'rows' => 4],
                ]],
            ]],
        ]],

        'alyuminievye' => $razdel('Алюминиевые окна', [
            ['title' => 'Виды: Alutech', 'fields' => [
                ['k' => 'typesByBrand.Alutech', 'type' => 'list', 'max' => 12, 'row' => 'title', 'sub' => 'text', 'thumb' => 'images', 'label' => 'Карточки видов', 'add' => 'Создать вид', 'item' => f_type_item()],
            ]],
            ['title' => 'Виды: Schuco', 'fields' => [
                ['k' => 'typesByBrand.Schuco', 'type' => 'list', 'max' => 12, 'row' => 'title', 'sub' => 'text', 'thumb' => 'images', 'label' => 'Карточки видов', 'add' => 'Создать вид', 'item' => f_type_item()],
            ]],
        ]),

        'pvh' => $razdel('Окна ПВХ', [
            ['title' => 'Виды', 'fields' => [
                ['k' => 'types', 'type' => 'list', 'max' => 12, 'row' => 'title', 'sub' => 'text', 'thumb' => 'images', 'label' => 'Карточки видов', 'add' => 'Создать вид', 'item' => f_type_item()],
            ]],
        ]),

        'works' => ['label' => 'Наши работы', 'groups' => [
            ['title' => 'Страница', 'fields' => [
                ['k' => 'lead', 'type' => 'text', 'max' => 200, 'label' => 'Подзаголовок'],
            ]],
            ['title' => 'Объекты', 'hint' => 'Нажмите на работу, чтобы изменить её. На главной показываются первые 5 работ, в разделах первые 4 по метке (Алюминий или ПВХ). Порядок меняется перетаскиванием карточек мышкой.', 'fields' => [
                ['k' => 'items', 'type' => 'list', 'max' => 60, 'row' => 'title', 'sub' => 'place', 'thumb' => 'images', 'label' => 'Список работ', 'add' => 'Создать работу', 'item' => [
                    ['k' => 'title', 'type' => 'text', 'label' => 'Название (коротко, до 36 знаков)', 'max' => 36, 'req' => true],
                    ['k' => 'place', 'type' => 'text', 'label' => 'Где и система (например: Домодедово · VEKA)', 'max' => 40],
                    ['k' => 'images', 'type' => 'gallery', 'max' => 8, 'label' => 'Фото (несколько: на сайте они листаются. Перетащите фото, чтобы изменить порядок; первое фото — обложка)'],
                    ['k' => 'tags', 'type' => 'tags', 'label' => 'Метки (для фильтра)'],
                ]],
            ]],
        ]],

        'tech' => ['label' => 'Техническая информация', 'groups' => [
            ['title' => 'Страница', 'fields' => [
                ['k' => 'lead', 'type' => 'text', 'max' => 200, 'label' => 'Подзаголовок'],
            ]],
            ['title' => 'Alutech', 'fields' => [
                ['k' => 'alutech.text', 'type' => 'textarea', 'max' => 260, 'rows' => 2, 'label' => 'Описание'],
                ['k' => 'alutech.files', 'type' => 'list', 'max' => 30, 'row' => 'title', 'sub' => 'size', 'label' => 'Каталоги', 'add' => 'Добавить каталог', 'item' => f_file_item()],
            ]],
            ['title' => 'Schuco', 'fields' => [
                ['k' => 'schuco.text', 'type' => 'textarea', 'max' => 260, 'rows' => 2, 'label' => 'Описание'],
                ['k' => 'schuco.files', 'type' => 'list', 'max' => 30, 'row' => 'title', 'sub' => 'size', 'label' => 'Каталоги', 'add' => 'Добавить каталог', 'item' => f_file_item()],
            ]],
            ['title' => 'VEKA', 'fields' => [
                ['k' => 'veka.text', 'type' => 'textarea', 'max' => 260, 'rows' => 2, 'label' => 'Описание'],
                ['k' => 'veka.profiles', 'type' => 'list', 'fixed' => true, 'row' => 'name', 'sub' => 'tag', 'thumb' => 'image', 'label' => 'Профили (3 штуки)', 'item' => [
                    ['k' => 'name', 'type' => 'text', 'label' => 'Название профиля', 'max' => 40, 'req' => true],
                    ['k' => 'tag', 'type' => 'text', 'label' => 'Метка (например, Универсальный)', 'max' => 30],
                    ['k' => 'text', 'type' => 'textarea', 'label' => 'Описание', 'max' => 160, 'rows' => 2],
                    ['k' => 'image', 'type' => 'image', 'label' => 'Рисунок профиля'],
                    ['k' => 'specs', 'type' => 'textarea', 'label' => 'Параметры (по одному в строке)', 'max' => 160, 'rows' => 3],
                ]],
                ['k' => 'veka.files', 'type' => 'list', 'max' => 30, 'row' => 'title', 'sub' => 'size', 'label' => 'Каталоги VEKA', 'add' => 'Добавить каталог', 'item' => f_file_item()],
            ]],
        ]],
    ];
}

// ---------- работа с путями «a.b.c» ----------
function path_get($data, string $path)
{
    foreach (explode('.', $path) as $k) {
        if (!is_array($data) || !array_key_exists($k, $data)) return null;
        $data = $data[$k];
    }
    return $data;
}

function path_set(array &$data, string $path, $value): void
{
    $ref = &$data;
    foreach (explode('.', $path) as $k) {
        if (!isset($ref[$k]) || !is_array($ref[$k])) $ref[$k] = [];
        $ref = &$ref[$k];
    }
    $ref = $value;
}

function fname(string $path, string $suffix = ''): string
{
    $parts = explode('.', $path);
    if ($suffix !== '') $parts[count($parts) - 1] .= $suffix;
    return 'd[' . implode('][', $parts) . ']';
}

// ---------- форма ----------
const IMG_ACCEPT = 'image/jpeg,image/png,image/webp';

function gallery_slot(string $name, string $fileName, string $url): string
{
    return '<div class="gal__slot" data-slot><img data-prev src="' . h($url) . '" alt="">'
        . '<input type="hidden" data-g-url name="' . h($name) . '" value="' . h($url) . '">'
        . '<input type="file" data-g-file name="' . h($fileName) . '" accept="' . IMG_ACCEPT . '" hidden>'
        . '<span class="gal__tools"><button type="button" data-gl title="Левее" aria-label="Левее">←</button><button type="button" data-gr title="Правее" aria-label="Правее">→</button><button type="button" data-gdel title="Убрать" aria-label="Убрать">✕</button></span></div>';
}

function render_field(array $f, $value, string $name, ?string $itemName = null): string
{
    $type = $f['type'];
    $label = h($f['label'] ?? '');
    $max = isset($f['max']) ? ' maxlength="' . (int)$f['max'] . '"' : '';
    $df = $itemName !== null ? ' data-f="' . h($itemName) . '"' : '';
    $nm = h($name);
    $v = is_array($value) ? '' : (string)$value;

    switch ($type) {
        case 'id':
            return '<input type="hidden"' . $df . ' name="' . $nm . '" value="' . h($v) . '">';
        case 'text':
        case 'link':
            $ph = $type === 'link' ? ' placeholder="/okna/pvh/ или https://…"' : '';
            return '<label>' . $label . '<input' . $df . ' name="' . $nm . '" value="' . h($v) . '"' . $max . $ph . '></label>';
        case 'textarea':
            return '<label>' . $label . '<textarea' . $df . ' name="' . $nm . '" rows="' . (int)($f['rows'] ?? 3) . '"' . $max . '>' . h($v) . '</textarea></label>';
        case 'lines':
            $o = '<fieldset class="lines"><legend>' . $label . '</legend>';
            for ($i = 0; $i < (int)($f['n'] ?? 2); $i++) {
                $o .= '<input name="' . $nm . '[' . $i . ']" value="' . h((string)(is_array($value) ? ($value[$i] ?? '') : '')) . '"' . $max . '>';
            }
            return $o . '</fieldset>';
        case 'image':
        case 'pdf':
            $isPdf = $type === 'pdf';
            $fileName = preg_replace('/\]$/', '__file]', $nm);
            $fdf = $itemName !== null ? ' data-f="' . h($itemName) . '__file"' : '';
            $prev = $isPdf ? '<span class="imgf__pdf">PDF</span>' : '<img src="' . h($v) . '" alt="" data-prev' . ($v === '' ? ' hidden' : '') . '>';
            return '<div class="imgf' . ($isPdf ? ' imgf--pdf' : '') . '">' . $prev . '<div class="imgf__body"><label>' . $label
                . '<input' . $df . ' name="' . $nm . '" value="' . h($v) . '" placeholder="' . ($isPdf ? 'Путь к файлу или загрузите PDF' : 'Ссылка на фото или загрузите файл') . '" data-url></label>'
                . '<label class="btn btn--ghost btn--sm">' . ($isPdf ? 'Загрузить PDF' : 'Загрузить фото')
                . '<input type="file"' . $fdf . ' name="' . h($fileName) . '" accept="' . ($isPdf ? 'application/pdf' : IMG_ACCEPT) . '" data-file hidden></label></div></div>';
        case 'gallery':
            $urls = is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
            $fileBase = preg_replace('/\]$/', '__file]', $nm);
            $o = '<div class="gal" data-gal data-f="' . h((string)$itemName) . '" data-max="' . (int)($f['max'] ?? 8) . '"><div class="gal__label">' . $label . '</div><div class="gal__grid">';
            foreach ($urls as $j => $u) $o .= gallery_slot($name . '[' . $j . ']', $fileBase . '[' . $j . ']', $u);
            $o .= '</div><template>' . gallery_slot($name . '[__J__]', $fileBase . '[__J__]', '') . '</template>'
                . '<div class="gal__add"><label class="btn btn--ghost btn--sm">+ Загрузить фото<input type="file" multiple accept="' . IMG_ACCEPT . '" data-gadd hidden></label>'
                . '<span class="gal__url"><input type="url" placeholder="или вставьте ссылку на фото" data-gurl><button type="button" class="btn btn--ghost btn--sm" data-gurl-add>Добавить</button></span></div></div>';
            return $o;
        case 'tags':
            $sel = is_array($value) ? $value : [];
            $o = '<fieldset class="tagsf"><legend>' . $label . '</legend>';
            foreach (TAG_OPTIONS as $k => $lab) {
                $o .= '<label class="check"><input type="checkbox" data-arr' . $df . ' name="' . $nm . '[]" value="' . h($k) . '"' . (in_array($k, $sel, true) ? ' checked' : '') . '> ' . h($lab) . '</label>';
            }
            return $o . '</fieldset>';
    }
    return '';
}

function render_list(array $f, $items, string $path): string
{
    $items = is_array($items) ? array_values($items) : [];
    $fixed = !empty($f['fixed']);
    $base = fname($path);
    $titleKey = $f['row'] ?? 'title';
    $subKey = $f['sub'] ?? '';
    $thumbKey = $f['thumb'] ?? '';
    $grid = $thumbKey !== '';

    $row = function (string $i, array $item) use ($f, $base, $fixed, $titleKey, $subKey, $thumbKey, $grid): string {
        $title = trim((string)($item[$titleKey] ?? ''));
        $sub = $subKey !== '' ? trim((string)($item[$subKey] ?? '')) : '';
        $tv = $thumbKey !== '' ? ($item[$thumbKey] ?? '') : '';
        $thumb = is_array($tv) ? (string)($tv[0] ?? '') : (string)$tv;
        $count = is_array($tv) ? count($tv) : 0;

        $o = '<div class="item" data-row><button type="button" class="item__main" data-edit>';
        if ($grid) {
            $o .= '<span class="item__thumb"><img data-thumb src="' . h($thumb) . '" alt=""' . ($thumb === '' ? ' hidden' : '') . '><i class="item__noimg"' . ($thumb !== '' ? ' hidden' : '') . '>Нет фото</i><em data-count' . ($count > 1 ? '' : ' hidden') . '>' . $count . ' фото</em></span>';
        }
        $o .= '<span class="item__txt"><strong data-t>' . h($title !== '' ? $title : 'Новый элемент') . '</strong><small data-s>' . h(mb_strimwidth($sub, 0, 90, '…')) . '</small></span>'
            . '<span class="item__go">Изменить</span></button>';
        if (!$fixed) {
            $o .= '<span class="item__tools"><button type="button" class="iconbtn" data-up title="Выше" aria-label="Выше">↑</button><button type="button" class="iconbtn" data-down title="Ниже" aria-label="Ниже">↓</button><button type="button" class="iconbtn iconbtn--del" data-del title="Удалить" aria-label="Удалить">✕</button></span>';
        }
        $o .= '<dialog class="dlg" data-dlg><div class="dlg__head"><strong>Редактирование<span class="dlg__sub" data-dtitle>' . h($title !== '' ? ': ' . $title : '') . '</span></strong><button type="button" class="iconbtn" data-close aria-label="Закрыть">✕</button></div><div class="dlg__body">';
        foreach ($f['item'] as $s) {
            $o .= render_field($s, $item[$s['k']] ?? (in_array($s['type'], ['tags', 'gallery'], true) ? [] : ''), $base . '[' . $i . '][' . $s['k'] . ']', $s['k']);
        }
        return $o . '</div><div class="dlg__foot"><button type="button" class="btn" data-close>Готово</button></div></dialog></div>';
    };

    $o = '<div class="list ' . ($grid ? 'list--grid' : 'list--rows') . '" data-list data-name="' . h($base) . '" data-row-key="' . h($titleKey) . '" data-sub-key="' . h($subKey) . '" data-thumb-key="' . h($thumbKey) . '"'
        . ($fixed ? ' data-fixed' : '') . ' data-max="' . (int)($f['max'] ?? 100) . '">';
    $o .= '<div class="list__head"><div><strong>' . h($f['label'] ?? '') . '</strong> <span class="muted" data-listcount>' . count($items) . '</span></div>';
    if (!$fixed) $o .= '<button type="button" class="btn btn--sm" data-add>+ ' . h($f['add'] ?? 'Создать') . '</button>';
    $o .= '</div><div class="list__rows">';
    foreach ($items as $i => $item) $o .= $row((string)$i, is_array($item) ? $item : []);
    $o .= '</div>';
    if (!$fixed) $o .= '<template>' . $row('__I__', []) . '</template>';
    return $o . '</div>';
}

function render_page_form(array $schema, array $data): string
{
    $o = '';
    foreach ($schema['groups'] as $g) {
        $o .= '<section class="panel"><h2>' . h($g['title']) . '</h2>';
        if (!empty($g['hint'])) $o .= '<p class="hint">' . h($g['hint']) . '</p>';
        $o .= '<div class="panel__body">';
        foreach ($g['fields'] as $f) {
            $val = path_get($data, $f['k']);
            $o .= $f['type'] === 'list' ? render_list($f, $val, $f['k']) : render_field($f, $val ?? '', fname($f['k']));
        }
        $o .= '</div></section>';
    }
    return $o;
}

// ---------- обработка формы ----------
function files_flat(): array
{
    $out = [];
    if (!isset($_FILES['d']['name']) || !is_array($_FILES['d']['name'])) return $out;
    $walk = function ($n, $t, $s, $e, string $path) use (&$walk, &$out): void {
        if (is_array($n)) {
            foreach ($n as $k => $_) $walk($n[$k], $t[$k], $s[$k], $e[$k], $path === '' ? (string)$k : $path . '.' . $k);
        } else {
            $out[$path] = ['name' => $n, 'tmp_name' => $t, 'size' => $s, 'error' => $e];
        }
    };
    $walk($_FILES['d']['name'], $_FILES['d']['tmp_name'], $_FILES['d']['size'], $_FILES['d']['error'], '');
    return $out;
}

function clean_str($v, int $max, bool $multiline = false): string
{
    $v = is_array($v) ? '' : trim((string)$v);
    $v = preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u' : '/[\x00-\x1F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}

function file_key(string $path, string $tail = ''): string
{
    $parts = explode('.', $path);
    $parts[count($parts) - 1] .= '__file';
    return implode('.', $parts) . $tail;
}

function clean_image_url(string $v, bool $isPdf = false): string
{
    $ok = $isPdf
        ? preg_match('#^(/docs/[A-Za-z0-9._/-]+\.pdf|https://\S+)$#', $v)
        : preg_match('#^(/(uploads|img)/[A-Za-z0-9._/-]+|https://\S+)$#', $v);
    return ($v === '' || $ok) ? $v : '';
}

// $path — путь к полю в данных (для ключей файлов), $errors — сюда складываем сообщения
function clean_field(array $f, $in, string $path, array $files, array &$errors)
{
    $max = (int)($f['max'] ?? ($f['type'] === 'textarea' ? 1000 : 200));
    switch ($f['type']) {
        case 'id':
            $v = is_string($in) ? $in : '';
            return preg_match('/^[a-z0-9-]{1,40}$/', $v) ? $v : 't' . bin2hex(random_bytes(3));
        case 'text':
            return clean_str($in, $max);
        case 'textarea':
            return clean_str($in, $max, true);
        case 'link':
            $v = clean_str($in, 200);
            if ($v !== '' && !preg_match('#^(/|https://|\#)#', $v)) { $errors[] = 'Ссылка должна начинаться с «/» или «https://»: ' . $v; return ''; }
            return $v;
        case 'lines':
            $out = [];
            for ($i = 0; $i < (int)($f['n'] ?? 2); $i++) $out[] = clean_str(is_array($in) ? ($in[$i] ?? '') : '', $max);
            return $out;
        case 'tags':
            return is_array($in) ? array_values(array_intersect(array_map('strval', $in), array_keys(TAG_OPTIONS))) : [];
        case 'image':
        case 'pdf':
            $isPdf = $f['type'] === 'pdf';
            $v = clean_str($in, 500);
            $file = $files[file_key($path)] ?? null;
            if ($file && ($file['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
                $saved = $isPdf ? save_pdf($file, 'catalog') : save_image($file, 'img');
                if ($saved) return $saved;
                $errors[] = $isPdf ? 'PDF не загружен: нужен PDF до 100 МБ (и не больше лимита хостинга).' : 'Фото не загружено: нужен jpg, png или webp до 4 МБ.';
            }
            return clean_image_url($v, $isPdf);
        case 'gallery':
            $out = [];
            $in = is_array($in) ? $in : [];
            ksort($in, SORT_NUMERIC);
            $limit = (int)($f['max'] ?? 8);
            foreach ($in as $j => $raw) {
                $url = clean_image_url(clean_str($raw, 500));
                $file = $files[file_key($path, '.' . $j)] ?? null;
                if ($file && ($file['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
                    $saved = save_image($file, 'img');
                    if ($saved) $url = $saved;
                    else $errors[] = 'Фото не загружено: нужен jpg, png или webp до 4 МБ.';
                }
                if ($url !== '') $out[] = $url;
                if (count($out) >= $limit) break;
            }
            return $out;
    }
    return null;
}

function clean_list(array $f, $in, $old, array $files, string $path, array &$errors): array
{
    $in = is_array($in) ? $in : [];
    $old = is_array($old) ? array_values($old) : [];
    $fixed = !empty($f['fixed']);
    $out = [];
    $i = 0;
    ksort($in, SORT_NUMERIC);
    foreach ($in as $row) {
        if (!is_array($row)) continue;
        $item = [];
        $blank = false;
        foreach ($f['item'] as $sub) {
            $item[$sub['k']] = clean_field($sub, $row[$sub['k']] ?? null, $path . '.' . $i . '.' . $sub['k'], $files, $errors);
            if (!empty($sub['req']) && $item[$sub['k']] === '') $blank = true;
        }
        $i++;
        if ($blank && !$fixed) continue; // пустые строки пропускаем
        $out[] = $item;
        if (count($out) >= (int)($f['max'] ?? 100)) break;
    }
    if ($fixed) { // количество не меняется: недостающие берём из прежних данных
        $n = count($old);
        $out = array_slice($out, 0, $n);
        for ($j = count($out); $j < $n; $j++) $out[] = $old[$j];
    }
    return $out;
}

function clean_page(array $schema, array $in, array $old, array $files, array &$errors): array
{
    $new = $old;
    foreach ($schema['groups'] as $g) {
        foreach ($g['fields'] as $f) {
            $raw = path_get($in, $f['k']);
            $value = $f['type'] === 'list'
                ? clean_list($f, $raw, path_get($old, $f['k']), $files, $f['k'], $errors)
                : clean_field($f, $raw, $f['k'], $files, $errors);
            path_set($new, $f['k'], $value);
        }
    }
    return $new;
}

// Пути загруженных через админку файлов (для удаления тех, что больше не используются)
function managed_files($data): array
{
    $out = [];
    $walk = function ($v) use (&$walk, &$out): void {
        if (is_array($v)) { foreach ($v as $x) $walk($x); return; }
        if (is_string($v) && preg_match('#^(/uploads/[a-z0-9-]+\.(jpg|png|webp)|/docs/files/[a-z0-9-]+\.pdf)$#', $v)) $out[$v] = true;
    };
    $walk($data);
    return array_keys($out);
}
