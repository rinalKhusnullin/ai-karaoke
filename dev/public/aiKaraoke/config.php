<?php
/**
 * Конфигурация AI Karaoke
 * Центральное место для всех настроек проекта
 */

class AIKaraokeConfig
{
    /**
     * OpenAI API ключ
     * Установите ваш реальный API ключ здесь
     */
    const OPENAI_API_KEY = '';

    /**
     * Настройки директорий
     */
    const UPLOAD_DIR = '/upload/aikaraoke/';
    const IMAGES_DIR = 'images/';

    /**
     * Настройки генерации изображений
     */
    const DALLE_MODEL = 'dall-e-3';
    const DALLE_SIZE = '1024x1024';
    const DALLE_QUALITY = 'standard';
    const DALLE_STYLE = 'vivid';

    /**
     * Настройки OpenAI Chat
     */
    const GPT_MODEL = 'gpt-4';
    const GPT_MAX_TOKENS = 1000;
    const GPT_TEMPERATURE = 0.3;

    /**
     * Настройки караоке
     */
    const LINES_PER_SLIDE = 3; // Количество строк на слайд
    const API_REQUEST_DELAY = 500000; // 0.5 секунды между запросами (в микросекундах)

    /**
     * Получить OpenAI API ключ
     * Сначала проверяет настройки Битрикс, затем константу
     */
    public static function getOpenAIKey()
    {
        // Проверяем настройки модуля Битрикс
        if (class_exists('\Bitrix\Main\Config\Option')) {
            $keyFromSettings = \Bitrix\Main\Config\Option::get('sign', 'openai_api_key', '');
            if (!empty($keyFromSettings)) {
                return $keyFromSettings;
            }
        }

        // Возвращаем ключ из константы
        return self::OPENAI_API_KEY;
    }

    /**
     * Проверить, настроен ли API ключ
     */
    public static function isAPIKeyConfigured()
    {
        $key = self::getOpenAIKey();
        return !empty($key) && strlen($key) > 20; // Минимальная длина для валидного ключа
    }

    /**
     * Получить путь к директории загрузок
     */
    public static function getUploadDir()
    {
        $uploadPath = $_SERVER["DOCUMENT_ROOT"] . self::UPLOAD_DIR;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        return $uploadPath;
    }

    /**
     * Получить путь к директории изображений
     */
    public static function getImagesDir()
    {
        $imagesPath = __DIR__ . '/' . self::IMAGES_DIR;
        if (!is_dir($imagesPath)) {
            mkdir($imagesPath, 0755, true);
        }
        return $imagesPath;
    }

    /**
     * Логирование отладочной информации
     */
    public static function debugLog($message, $context = [])
    {
        $logMessage = '[' . date('Y-m-d H:i:s') . '] AI Karaoke: ' . $message;

        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $logMessage .= PHP_EOL;

        // Пишем в файл отладки
        file_put_contents(__DIR__ . '/debug.log', $logMessage, FILE_APPEND | LOCK_EX);

        // Также в error_log
        error_log('AI Karaoke: ' . $message);
    }

    /**
     * Создать необходимые директории
     */
    public static function createDirectories()
    {
        $uploadDir = self::getUploadDir();
        $imagesDir = self::getImagesDir();

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            self::debugLog('Created upload directory: ' . $uploadDir);
        }

        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
            self::debugLog('Created images directory: ' . $imagesDir);
        }

        return [
            'upload_dir' => $uploadDir,
            'images_dir' => $imagesDir,
            'upload_writable' => is_writable($uploadDir),
            'images_writable' => is_writable($imagesDir)
        ];
    }
}
