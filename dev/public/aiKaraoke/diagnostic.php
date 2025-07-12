<?php
// Простая проверка функциональности generate_karaoke.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

echo "<h1>Диагностика проблем генерации караоке</h1>";

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';

echo "<h3>Проверка конфигурации:</h3>";
echo "<p>API ключ настроен: " . (AIKaraokeConfig::isAPIKeyConfigured() ? "✅ ДА" : "❌ НЕТ") . "</p>";
echo "<p>Длина API ключа: " . strlen(AIKaraokeConfig::getOpenAIKey()) . "</p>";

$uploadDir = AIKaraokeConfig::getUploadDir();
$imagesDir = AIKaraokeConfig::getImagesDir();

echo "<p>Директория загрузок: " . $uploadDir . "</p>";
echo "<p>Права на загрузки: " . (is_writable($uploadDir) ? "✅ ДА" : "❌ НЕТ") . "</p>";
echo "<p>Директория изображений: " . $imagesDir . "</p>";
echo "<p>Права на изображения: " . (is_writable($imagesDir) ? "✅ ДА" : "❌ НЕТ") . "</p>";

echo "<h3>Проверка класса генератора:</h3>";

try {
    require_once __DIR__ . '/generate_karaoke.php';
    echo "<p>✅ Файл generate_karaoke.php загружен успешно</p>";

    if (class_exists('KaraokeGenerator')) {
        echo "<p>✅ Класс KaraokeGenerator найден</p>";

        $generator = new KaraokeGenerator();
        echo "<p>✅ Экземпляр класса создан успешно</p>";
    } else {
        echo "<p>❌ Класс KaraokeGenerator не найден</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Ошибка при загрузке: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>❌ Стек: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}

echo "<h3>Проверка последних ошибок PHP:</h3>";
$lastError = error_get_last();
if ($lastError) {
    echo "<pre>";
    echo "Тип: " . $lastError['type'] . "\n";
    echo "Сообщение: " . htmlspecialchars($lastError['message']) . "\n";
    echo "Файл: " . $lastError['file'] . "\n";
    echo "Строка: " . $lastError['line'] . "\n";
    echo "</pre>";
} else {
    echo "<p>✅ Ошибок PHP не обнаружено</p>";
}

echo "<h3>Проверка модулей Битрикс:</h3>";
if (class_exists('\Bitrix\Main\Loader')) {
    echo "<p>✅ Bitrix Loader доступен</p>";

    try {
        \Bitrix\Main\Loader::requireModule('sign');
        echo "<p>✅ Модуль 'sign' загружен</p>";
    } catch (Exception $e) {
        echo "<p>❌ Ошибка загрузки модуля 'sign': " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>❌ Bitrix Loader недоступен</p>";
}

echo "<h3>Тест минимального API запроса:</h3>";
if (AIKaraokeConfig::isAPIKeyConfigured()) {
    echo "<p>Отправляем тестовый запрос к OpenAI...</p>";

    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => 'Say hello']
        ],
        'max_tokens' => 10
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AIKaraokeConfig::getOpenAIKey()
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<p>HTTP код ответа: " . $httpCode . "</p>";
    if ($httpCode === 200) {
        echo "<p>✅ API ключ работает!</p>";
    } else {
        echo "<p>❌ Проблема с API ключом. Ответ: " . htmlspecialchars($response) . "</p>";
    }
} else {
    echo "<p>❌ API ключ не настроен</p>";
}

echo '<p style="margin-top: 30px;">';
echo '<a href="index.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🏠 Вернуться на главную</a>';
echo '</p>';
?>
