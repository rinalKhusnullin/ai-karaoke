<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
?>

<div style="max-width: 1200px; margin: 20px auto; padding: 20px;">
    <h1>AI Караоке - Отладка</h1>

    <div style="margin-bottom: 20px;">
        <h3>Последние 50 записей лога отладки:</h3>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 14px; max-height: 600px; overflow-y: auto;">
            <?php
            $logFile = $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/logs/error.log';

            if (file_exists($logFile)) {
                $lines = file($logFile);
                $debugLines = array_filter($lines, function($line) {
                    return strpos($line, 'AI Karaoke Debug') !== false;
                });

                $debugLines = array_slice($debugLines, -50); // Последние 50 записей

                if (empty($debugLines)) {
                    echo "<p style='color: #666;'>Нет записей отладки AI Караоке в логах.</p>";
                } else {
                    foreach (array_reverse($debugLines) as $line) {
                        $line = htmlspecialchars(trim($line));

                        // Выделяем разные типы сообщений цветом
                        if (strpos($line, 'Failed') !== false || strpos($line, 'error') !== false) {
                            echo "<div style='color: #dc3545; margin-bottom: 5px;'>$line</div>";
                        } elseif (strpos($line, 'successfully') !== false) {
                            echo "<div style='color: #28a745; margin-bottom: 5px;'>$line</div>";
                        } elseif (strpos($line, 'Starting') !== false || strpos($line, 'completed') !== false) {
                            echo "<div style='color: #007bff; margin-bottom: 5px;'>$line</div>";
                        } else {
                            echo "<div style='color: #6c757d; margin-bottom: 5px;'>$line</div>";
                        }
                    }
                }
            } else {
                echo "<p style='color: #dc3545;'>Файл лога не найден: $logFile</p>";
            }
            ?>
        </div>
    </div>

    <div style="margin-bottom: 20px;">
        <h3>Проверка настроек:</h3>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
            <?php
            \Bitrix\Main\Loader::requireModule('sign');

            $apiKey = \Bitrix\Main\Config\Option::get('sign', 'openai_api_key', '');
            $uploadDir = $_SERVER["DOCUMENT_ROOT"] . "/upload/aikaraoke/";
            $imagesDir = __DIR__ . "/images/";

            echo "<p><strong>API ключ OpenAI:</strong> " . (!empty($apiKey) ? '✅ Установлен' : '❌ Не установлен') . "</p>";
            echo "<p><strong>Директория uploads:</strong> " . (is_dir($uploadDir) ? '✅ Существует' : '❌ Не существует') . " ($uploadDir)</p>";
            echo "<p><strong>Директория изображений:</strong> " . (is_dir($imagesDir) ? '✅ Существует' : '❌ Не существует') . " ($imagesDir)</p>";
            echo "<p><strong>Права на запись uploads:</strong> " . (is_writable($uploadDir) ? '✅ Есть' : '❌ Нет') . "</p>";
            echo "<p><strong>Права на запись изображений:</strong> " . (is_writable($imagesDir) ? '✅ Есть' : '❌ Нет') . "</p>";
            echo "<p><strong>Функция file_get_contents:</strong> " . (function_exists('file_get_contents') ? '✅ Доступна' : '❌ Недоступна') . "</p>";
            echo "<p><strong>Функция curl:</strong> " . (function_exists('curl_init') ? '✅ Доступна' : '❌ Недоступна') . "</p>";

            // Показываем содержимое директории изображений
            if (is_dir($imagesDir)) {
                $imageFiles = glob($imagesDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                echo "<p><strong>Сгенерированные изображения:</strong> " . count($imageFiles) . " файлов</p>";
                if (count($imageFiles) > 0) {
                    echo "<details style='margin-top: 10px;'>";
                    echo "<summary>Показать файлы изображений</summary>";
                    echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
                    foreach (array_slice($imageFiles, -10) as $file) {
                        $filename = basename($file);
                        $filesize = round(filesize($file) / 1024, 1);
                        echo "<li>$filename ({$filesize} KB)</li>";
                    }
                    echo "</ul>";
                    echo "</details>";
                }
            }
            ?>
        </div>
    </div>

    <div style="margin-bottom: 20px;">
        <h3>Действия:</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="location.reload()" style="background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">
                Обновить логи
            </button>
            <a href="settings.php" style="background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; text-decoration: none; display: inline-block;">
                Настройки API
            </a>
            <a href="index.php" style="background: #6c757d; color: white; padding: 10px 15px; border: none; border-radius: 4px; text-decoration: none; display: inline-block;">
                Главная страница
            </a>
        </div>
    </div>

    <div style="background: #e9ecef; padding: 15px; border-radius: 8px;">
        <h4>Что проверить если изображения не генерируются:</h4>
        <ol>
            <li><strong>API ключ:</strong> Убедитесь что ключ OpenAI установлен и действителен</li>
            <li><strong>Баланс OpenAI:</strong> Проверьте баланс на платформе OpenAI</li>
            <li><strong>Права доступа:</strong> Директория для изображений должна иметь права на запись</li>
            <li><strong>Логи ошибок:</strong> Ищите ошибки HTTP кодов в логах выше</li>
            <li><strong>Сеть:</strong> Проверьте что сервер может обращаться к API OpenAI</li>
            <li><strong>Промпты:</strong> Убедитесь что промпты не нарушают политику OpenAI</li>
        </ol>
    </div>
</div>

<script>
// Автообновление логов каждые 10 секунд
setInterval(() => {
    const debugSection = document.querySelector('div[style*="font-family: monospace"]');
    if (debugSection && document.visibilityState === 'visible') {
        // Сохраняем позицию скролла
        const scrollTop = debugSection.scrollTop;

        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.querySelector('div[style*="font-family: monospace"]');
                if (newContent) {
                    debugSection.innerHTML = newContent.innerHTML;
                    debugSection.scrollTop = scrollTop;
                }
            })
            .catch(console.error);
    }
}, 10000);
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
