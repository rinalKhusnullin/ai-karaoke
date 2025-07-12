<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loader::requireModule('sign');

// Обработка сохранения настроек
if ($_POST['save_settings'] && check_bitrix_sessid()) {
    $openaiKey = trim($_POST['openai_key']);
    Option::set('sign', 'openai_api_key', $openaiKey);

    $APPLICATION->AddHeadString('<script>alert("Настройки сохранены!");</script>');
}

// Получаем текущий ключ
$currentKey = Option::get('sign', 'openai_api_key', '');

?>

<div style="max-width: 800px; margin: 20px auto; padding: 20px;">
    <h1>Настройки ИИ Караоке</h1>

    <form method="post">
        <?= bitrix_sessid_post() ?>

        <div style="margin-bottom: 20px;">
            <label for="openai_key" style="display: block; margin-bottom: 5px; font-weight: bold;">
                OpenAI API Ключ:
            </label>
            <input
                type="password"
                id="openai_key"
                name="openai_key"
                value="<?= htmlspecialchars($currentKey) ?>"
                style="width: 100%; max-width: 500px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                placeholder="sk-..."
            >
            <div style="margin-top: 5px; color: #666; font-size: 14px;">
                Получите API ключ на <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
            </div>
        </div>

        <button
            type="submit"
            name="save_settings"
            value="1"
            style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;"
        >
            Сохранить настройки
        </button>
    </form>

    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3>Как это работает:</h3>
        <ol>
            <li>Загрузите минусовку (аудио без вокала) и плюсовку (аудио с вокалом)</li>
            <li>Введите текст песни, каждая строка на новой строке</li>
            <li>ИИ проанализирует длительность трека и создаст оптимальные тайминги для каждой строки</li>
            <li>Получите готовые слайды с синхронизацией под музыку</li>
        </ol>

        <h3>Требования:</h3>
        <ul>
            <li>OpenAI API ключ с доступом к GPT-4</li>
            <li>Аудио файлы в поддерживаемых форматах (MP3, WAV, etc.)</li>
            <li>FFprobe для определения длительности аудио (опционально)</li>
        </ul>
    </div>
</div>

<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
