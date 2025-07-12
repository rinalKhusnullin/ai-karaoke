<?php
// Быстрая настройка API ключа OpenAI
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loader::requireModule('sign');

// ЗАМЕНИТЕ НА ВАШ РЕАЛЬНЫЙ API КЛЮЧ
$openaiApiKey = '';

// Сохраняем в настройки модуля
Option::set('sign', 'openai_api_key', $openaiApiKey);

echo "API ключ OpenAI успешно установлен!";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
