# AI KARAOKE Blood

## ���������

����� ��������� �����

`.htaccess`
��������
```
#RewriteCond %{REQUEST_FILENAME} !/bitrix/urlrewrite.php$
#RewriteRule ^(.*)$ /bitrix/urlrewrite.php [L]
```

��
```
RewriteCond %{REQUEST_FILENAME} !/bitrix/routing_index.php$
RewriteRule ^(.*)$ /bitrix/routing_index.php [L]
```

��������� ����� �������

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

� �� ������ ������������� ������