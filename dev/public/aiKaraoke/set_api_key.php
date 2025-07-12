<?php
// Простая настройка API ключа OpenAI через централизованную конфигурацию
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loader::requireModule('sign');

if (!empty($_POST['api_key'])) {
    // Сохраняем API ключ в настройки модуля
    $apiKey = trim($_POST['api_key']);
    Option::set('sign', 'openai_api_key', $apiKey);

    AIKaraokeConfig::debugLog('API key updated via settings page', [
        'key_length' => strlen($apiKey),
        'is_configured' => AIKaraokeConfig::isAPIKeyConfigured()
    ]);

    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "✅ API ключ OpenAI успешно сохранен!";
    echo "</div>";
}

$currentKey = AIKaraokeConfig::getOpenAIKey();
$isConfigured = AIKaraokeConfig::isAPIKeyConfigured();

?>

<div style="max-width: 600px; margin: 20px auto; padding: 20px; font-family: Arial, sans-serif;">
    <h2>🔑 Настройка API ключа OpenAI</h2>

    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <h3>Текущий статус:</h3>
        <p><strong>API ключ настроен:</strong> <?= $isConfigured ? '✅ ДА' : '❌ НЕТ' ?></p>
        <?php if ($isConfigured): ?>
            <p><strong>Длина ключа:</strong> <?= strlen($currentKey) ?> символов</p>
            <p><strong>Префикс:</strong> <?= substr($currentKey, 0, 10) ?>...</p>
        <?php endif; ?>
    </div>

    <form method="POST" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
        <h3>Установить новый API ключ:</h3>
        <p style="color: #666; font-size: 14px;">
            Получите API ключ на <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
        </p>

        <div style="margin: 15px 0;">
            <label for="api_key" style="display: block; margin-bottom: 8px; font-weight: bold;">
                OpenAI API Key:
            </label>
            <input
                type="password"
                id="api_key"
                name="api_key"
                placeholder="sk-..."
                style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-family: monospace;"
                value="<?= htmlspecialchars($currentKey) ?>"
            >
        </div>

        <button
            type="submit"
            style="background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;"
        >
            💾 Сохранить ключ
        </button>
    </form>

    <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 14px;">
        <h4>💡 Важная информация:</h4>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>API ключ сохраняется в настройках модуля Битрикс</li>
            <li>Также можно задать ключ в файле config.php (константа OPENAI_API_KEY)</li>
            <li>Настройки модуля имеют приоритет над файлом конфигурации</li>
            <li>Без API ключа будут создаваться только placeholder изображения</li>
        </ul>
    </div>

    <div style="margin: 20px 0;">
        <a href="test_images.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">
            🧪 Тестировать генерацию изображений
        </a>
        <a href="index.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            🏠 Главная страница
        </a>
    </div>
</div>

<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
