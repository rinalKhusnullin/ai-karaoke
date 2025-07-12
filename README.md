# AI KARAOKE Blood

## Установка

После установки нужно

`.htaccess`
Заменяем
```
#RewriteCond %{REQUEST_FILENAME} !/bitrix/urlrewrite.php$
#RewriteRule ^(.*)$ /bitrix/urlrewrite.php [L]
```

На
```
RewriteCond %{REQUEST_FILENAME} !/bitrix/routing_index.php$
RewriteRule ^(.*)$ /bitrix/routing_index.php [L]
```

Добавляем новый роутинг

`.settings`
```
'routing' => 
  array (
    'value' => 
    array (
      'config' => 
      array (
        1 => 'AIKaraoke.php',
        2 => 'SignSafe.Api.php',
      ),
    ),
    'readonly' => false,
  ),
```

И на всякий перезагружаем сервак