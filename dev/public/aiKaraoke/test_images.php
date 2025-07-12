<?php
// Простой тест генерации изображений DALL-E
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

$openaiKey = '';

echo "<h1>Тест генерации изображений DALL-E</h1>";

// Проверяем базовые настройки
echo "<h3>Проверка настроек:</h3>";
echo "<p>API ключ установлен: " . (!empty($openaiKey) ? "✅ ДА" : "❌ НЕТ") . "</p>";
echo "<p>Длина ключа: " . strlen($openaiKey) . "</p>";
echo "<p>CURL доступен: " . (function_exists('curl_init') ? "✅ ДА" : "❌ НЕТ") . "</p>";

$imagesDir = __DIR__ . "/images/";
echo "<p>Директория изображений: " . $imagesDir . "</p>";
echo "<p>Директория существует: " . (is_dir($imagesDir) ? "✅ ДА" : "❌ НЕТ") . "</p>";
echo "<p>Права на запись: " . (is_writable($imagesDir) ? "✅ ДА" : "❌ НЕТ") . "</p>";

if (!empty($_GET['test'])) {
    echo "<h3>Тестирование генерации изображения:</h3>";

    $prompt = "A beautiful sunset over mountains, cinematic style, vibrant colors";

    echo "<p><strong>Промпт:</strong> " . htmlspecialchars($prompt) . "</p>";

    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => '1024x1024',
        'quality' => 'standard',
        'style' => 'vivid'
    ];

    echo "<p>Отправляем запрос к DALL-E API...</p>";
    flush();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/images/generations');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "<p><strong>HTTP код ответа:</strong> " . $httpCode . "</p>";

    if (!empty($curlError)) {
        echo "<p><strong>CURL ошибка:</strong> " . htmlspecialchars($curlError) . "</p>";
    }

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['data'][0]['url'])) {
            $imageUrl = $result['data'][0]['url'];
            echo "<p><strong>✅ Изображение успешно сгенерировано!</strong></p>";
            echo "<p><strong>URL изображения:</strong> <a href='" . htmlspecialchars($imageUrl) . "' target='_blank'>" . htmlspecialchars($imageUrl) . "</a></p>";

            // Пробуем скачать изображение
            echo "<p>Пробуем скачать изображение...</p>";

            $imageData = file_get_contents($imageUrl);
            if ($imageData !== false) {
                $filename = 'test_' . time() . '.png';
                $filepath = $imagesDir . $filename;

                if (file_put_contents($filepath, $imageData)) {
                    echo "<p><strong>✅ Изображение успешно сохранено:</strong> " . $filename . "</p>";
                    echo "<p><strong>Размер файла:</strong> " . round(strlen($imageData) / 1024, 1) . " KB</p>";
                    echo "<img src='images/" . $filename . "' style='max-width: 300px; border-radius: 8px; margin: 10px 0;' alt='Test image'>";
                } else {
                    echo "<p><strong>❌ Ошибка сохранения файла</strong></p>";
                }
            } else {
                echo "<p><strong>❌ Ошибка скачивания изображения</strong></p>";
            }
        } else {
            echo "<p><strong>❌ В ответе нет URL изображения</strong></p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    } else {
        echo "<p><strong>❌ Ошибка API:</strong> HTTP " . $httpCode . "</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

if (empty($_GET['test'])) {
    echo '<p><a href="?test=1" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🧪 Запустить тест генерации</a></p>';
}

// Показываем последние сгенерированные изображения
$imageFiles = glob($imagesDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
if (!empty($imageFiles)) {
    echo "<h3>Последние сгенерированные изображения:</h3>";
    foreach (array_slice($imageFiles, -5) as $file) {
        $filename = basename($file);
        $filesize = round(filesize($file) / 1024, 1);
        echo "<div style='margin: 10px 0;'>";
        echo "<img src='images/" . $filename . "' style='max-width: 200px; border-radius: 8px; margin-right: 10px;' alt='" . $filename . "'>";
        echo "<br><small>" . $filename . " (" . $filesize . " KB)</small>";
        echo "</div>";
    }
}

echo '<p><a href="debug.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">📋 Открыть полные логи отладки</a></p>';
echo '<p><a href="index.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🏠 Главная страница</a></p>';
?>
