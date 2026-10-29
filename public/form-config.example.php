<?php
// Скопируйте в form-config.php на сервере и укажите реальные адреса.
// form-config.php не должен попадать в публичный репозиторий.
return [
    'to'        => 'leads@example.com',        // TODO: e-mail, куда приходят заявки
    'from'      => 'no-reply@oknatrio.ru',     // отправитель (домен сайта, чтобы письма не уходили в спам)
    'site_name' => 'oknatrio.ru',
];
