<?php

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Web\Json;

// НЕ подключаем header и footer для чистого JSON ответа
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';

\Bitrix\Main\Loader::requireModule('sign');

class KaraokeGenerator
{
    private $uploadDir;
    private $imagesDir;

    public function __construct()
    {
        // Используем централизованную конфигурацию
        $this->uploadDir = AIKaraokeConfig::getUploadDir();
        $this->imagesDir = AIKaraokeConfig::getImagesDir();

        // Создаем директории через конфигурацию
        $directories = AIKaraokeConfig::createDirectories();

        AIKaraokeConfig::debugLog('Karaoke Generator initialized', [
            'api_key_configured' => AIKaraokeConfig::isAPIKeyConfigured(),
            'directories' => $directories
        ]);
    }

    private function debugLog($message)
    {
        $logMessage = '[' . date('Y-m-d H:i:s') . '] AI Karaoke Debug: ' . $message . PHP_EOL;

        // Пишем в несколько мест для надежности
        error_log('AI Karaoke Debug: ' . $message);
        file_put_contents(__DIR__ . '/debug.log', $logMessage, FILE_APPEND | LOCK_EX);

        // Также в стандартный лог PHP
        if (function_exists('error_log')) {
            error_log($logMessage, 3, '/tmp/aikaraoke_debug.log');
        }
    }

    public function processRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->errorResponse('Метод не поддерживается');
        }

        try {
            // Проверяем наличие файлов и текста
            if (!isset($_FILES['minus_file']) || !isset($_FILES['plus_file']) || empty($_POST['lyrics'])) {
                return $this->errorResponse('Отсутствуют необходимые данные');
            }

            // Сохраняем загруженные файлы
            $minusPath = $this->saveUploadedFile($_FILES['minus_file'], 'minus');
            $plusPath = $this->saveUploadedFile($_FILES['plus_file'], 'plus');

            if (!$minusPath || !$plusPath) {
                return $this->errorResponse('Ошибка сохранения файлов');
            }

            $lyrics = $_POST['lyrics'];

            // Анализируем аудио и генерируем тайминги с помощью ИИ
            $karaokeData = $this->generateKaraokeWithAI($minusPath, $plusPath, $lyrics);

            if (!$karaokeData) {
                return $this->errorResponse('Ошибка генерации караоке');
            }

            return $this->successResponse($karaokeData);

        } catch (Exception $e) {
            return $this->errorResponse('Внутренняя ошибка: ' . $e->getMessage());
        }
    }

    private function saveUploadedFile($file, $prefix)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $this->uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filepath;
        }

        return false;
    }

    private function generateKaraokeWithAI($minusPath, $plusPath, $lyrics)
    {
        // Получаем длительность аудио файла
        $audioDuration = $this->getAudioDuration($minusPath);

        if (!$audioDuration) {
            return false;
        }

        // Разбиваем текст на строки
        $lyricsLines = array_filter(array_map('trim', explode("\n", $lyrics)));

        // Группируем строки по 3 в один слайд
        $groupedSlides = $this->groupLinesIntoSlides($lyricsLines, 3);

        $this->debugLog('Original lines: ' . count($lyricsLines) . ', Grouped slides: ' . count($groupedSlides));

        // Генерируем тайминги с помощью OpenAI для сгруппированных слайдов
        $timings = $this->generateTimingsWithOpenAI($groupedSlides, $audioDuration);

        if (!$timings) {
            // Если ИИ недоступен, используем равномерное распределение
            $timings = $this->generateEvenTimings($groupedSlides, $audioDuration);
        }

        // Генерируем изображения для каждого слайда
        $images = $this->generateImagesForSlides($groupedSlides);

        // Создаем слайды
        $slides = [];
        foreach ($groupedSlides as $index => $slideText) {
            $slides[] = [
                'text' => $slideText,
                'start' => $timings[$index]['start'],
                'end' => $timings[$index]['end'],
                'image' => isset($images[$index]) ? $images[$index] : null
            ];
        }

        return [
            'slides' => $slides,
            'timeline' => $timings,
            'audio_url' => $this->getRelativeUrl($minusPath),
            'duration' => $audioDuration
        ];
    }

    private function groupLinesIntoSlides($lyricsLines, $linesPerSlide = null)
    {
        if ($linesPerSlide === null) {
            $linesPerSlide = AIKaraokeConfig::LINES_PER_SLIDE;
        }

        $slides = [];
        $currentSlide = [];

        foreach ($lyricsLines as $line) {
            $currentSlide[] = $line;

            // Если набрали нужное количество строк, создаем слайд
            if (count($currentSlide) >= $linesPerSlide) {
                $slides[] = implode("\n", $currentSlide);
                $currentSlide = [];
            }
        }

        // Добавляем оставшиеся строки, если есть
        if (!empty($currentSlide)) {
            $slides[] = implode("\n", $currentSlide);
        }

        return $slides;
    }

    private function generateImagesForSlides($lyricsLines)
    {
        AIKaraokeConfig::debugLog('Starting image generation for ' . count($lyricsLines) . ' slides');

        if (!AIKaraokeConfig::isAPIKeyConfigured()) {
            AIKaraokeConfig::debugLog('No OpenAI API key configured, skipping image generation');
            return [];
        }

        $images = [];
        $fullLyrics = implode(' ', $lyricsLines);

        // Генерируем общую тему песни для контекста
        $songTheme = $this->analyzeSongTheme($fullLyrics);
        AIKaraokeConfig::debugLog('Song theme detected: ' . $songTheme);

        foreach ($lyricsLines as $index => $line) {
            AIKaraokeConfig::debugLog('Generating image for slide ' . $index . ': "' . $line . '"');

            $imageUrl = $this->generateImageForLine($line, $songTheme, $index);
            if ($imageUrl) {
                $images[$index] = $imageUrl;
                AIKaraokeConfig::debugLog('Image generated successfully for slide ' . $index . ': ' . $imageUrl);
            } else {
                AIKaraokeConfig::debugLog('Failed to generate image for slide ' . $index);
            }

            // Используем задержку из конфигурации
            usleep(AIKaraokeConfig::API_REQUEST_DELAY);
        }

        AIKaraokeConfig::debugLog('Image generation completed. Generated: ' . count($images) . ' images');
        return $images;
    }

    private function analyzeSongTheme($fullLyrics)
    {
        if (!AIKaraokeConfig::isAPIKeyConfigured()) {
            return "musical atmosphere";
        }

        // Делаем промпт на английском для лучшей работы с API
        $prompt = "Analyze the song lyrics and determine the main theme, mood and visual style. Describe in a few key words for image generation:\n\n" . $fullLyrics;

        $data = [
            'model' => AIKaraokeConfig::GPT_MODEL,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert in analyzing musical texts. Determine theme and mood for creating visual images. Answer briefly in English, maximum 50 words.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 100,
            'temperature' => AIKaraokeConfig::GPT_TEMPERATURE
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

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $theme = trim($result['choices'][0]['message']['content']);
                AIKaraokeConfig::debugLog('Full theme response: ' . $theme);
                return $theme;
            }
        }

        return "musical atmosphere, urban lifestyle, luxury, success";
    }

    private function generateImageForLine($line, $songTheme, $index)
    {
        if (!AIKaraokeConfig::isAPIKeyConfigured()) {
            AIKaraokeConfig::debugLog('No API key for image generation');
            return $this->generatePlaceholderImage($line, $index);
        }

        // Создаем промпт для генерации изображения
        $prompt = $this->createImagePrompt($line, $songTheme);
        AIKaraokeConfig::debugLog('Image prompt for slide ' . $index . ': ' . $prompt);

        $data = [
            'model' => AIKaraokeConfig::DALLE_MODEL,
            'prompt' => $prompt,
            'n' => 1,
            'size' => AIKaraokeConfig::DALLE_SIZE,
            'quality' => AIKaraokeConfig::DALLE_QUALITY,
            'style' => AIKaraokeConfig::DALLE_STYLE
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/images/generations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AIKaraokeConfig::getOpenAIKey()
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        AIKaraokeConfig::debugLog('DALL-E API response code: ' . $httpCode);

        if (!empty($curlError)) {
            AIKaraokeConfig::debugLog('CURL error: ' . $curlError);
        }

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['data'][0]['url'])) {
                $imageUrl = $result['data'][0]['url'];
                AIKaraokeConfig::debugLog('Image URL received: ' . $imageUrl);

                // Скачиваем и сохраняем изображение локально
                $localUrl = $this->saveImageLocally($imageUrl, $index);
                if ($localUrl) {
                    AIKaraokeConfig::debugLog('Image saved locally: ' . $localUrl);
                    return $localUrl;
                } else {
                    AIKaraokeConfig::debugLog('Failed to save image locally');
                }
            } else {
                AIKaraokeConfig::debugLog('No image URL in response: ' . $response);
            }
        } else {
            // Логируем ошибку генерации изображения
            AIKaraokeConfig::debugLog('Image generation failed for line: "' . $line . '". HTTP Code: ' . $httpCode . '. Response: ' . $response);
        }

        // Если DALL-E не сработал, создаем placeholder
        return $this->generatePlaceholderImage($line, $index);
    }

    private function createImagePrompt($line, $songTheme)
    {
        // Убираем лишние символы и создаем описательный промпт
        $cleanLine = preg_replace('/[^\p{L}\p{N}\s]/u', '', $line);

        // Переводим на английский для лучшего качества генерации
        $englishLine = $this->translateToEnglish($cleanLine);
        $englishTheme = $this->translateToEnglish($songTheme);

        $prompt = "Create a beautiful, artistic image that represents the concept: \"$englishLine\". ";
        $prompt .= "Overall theme and mood: $englishTheme. ";
        $prompt .= "Style: cinematic, vibrant colors, emotional, abstract art, suitable for music visualization. ";
        $prompt .= "NO TEXT, NO WORDS, NO LETTERS in the image. ";
        $prompt .= "Focus on mood, atmosphere, colors and abstract visual metaphors. ";
        $prompt .= "Professional photography style, high quality, artistic composition.";

        return $prompt;
    }

    private function translateToEnglish($text)
    {
        // Простой словарь для базового перевода ключевых слов
        $translations = [
            'любовь' => 'love',
            'сердце' => 'heart',
            'город' => 'city',
            'небо' => 'sky',
            'дорога' => 'road',
            'машина' => 'car',
            'деньги' => 'money',
            'богатство' => 'wealth',
            'роскошь' => 'luxury',
            'успех' => 'success',
            'борьба' => 'struggle',
            'триумф' => 'triumph',
            'ностальгия' => 'nostalgia',
            'рефлексия' => 'reflection',
            'гордость' => 'pride',
            'решимость' => 'determination',
            'непреклонность' => 'steadfastness',
            'быстрый темп' => 'fast pace',
            'жизнь' => 'life',
            'проблемы' => 'problems',
            'трудности' => 'difficulties',
            'путь' => 'path',
            'осень' => 'autumn',
            'цвета' => 'colors',
            'музыкальная атмосфера' => 'musical atmosphere'
        ];

        $englishText = $text;
        foreach ($translations as $russian => $english) {
            $englishText = str_ireplace($russian, $english, $englishText);
        }

        // Если не удалось перевести, используем общие термины
        if (mb_strlen($englishText) > 0 && preg_match('/[а-яё]/iu', $englishText)) {
            return "abstract artistic concept, emotional atmosphere, cinematic mood";
        }

        return $englishText;
    }

    private function saveImageLocally($imageUrl, $index)
    {
        try {
            AIKaraokeConfig::debugLog('Attempting to download image from: ' . $imageUrl);

            // Создаем контекст для file_get_contents с таймаутом
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (compatible; AI Karaoke Generator)'
                ]
            ]);

            $imageData = file_get_contents($imageUrl, false, $context);
            if ($imageData === false) {
                AIKaraokeConfig::debugLog('Failed to download image from URL');
                return null;
            }

            AIKaraokeConfig::debugLog('Image downloaded, size: ' . strlen($imageData) . ' bytes');

            $filename = 'slide_' . $index . '_' . time() . '.png';
            $filepath = $this->imagesDir . $filename;

            AIKaraokeConfig::debugLog('Saving image to: ' . $filepath);

            // Проверяем права на запись в директорию
            if (!is_writable($this->imagesDir)) {
                AIKaraokeConfig::debugLog('Images directory is not writable: ' . $this->imagesDir);
                return null;
            }

            if (file_put_contents($filepath, $imageData)) {
                $relativeUrl = $this->getRelativeUrl($filepath);
                AIKaraokeConfig::debugLog('Image saved successfully. Relative URL: ' . $relativeUrl);
                return $relativeUrl;
            } else {
                AIKaraokeConfig::debugLog('Failed to write image file');
            }
        } catch (Exception $e) {
            AIKaraokeConfig::debugLog('Exception in saveImageLocally: ' . $e->getMessage());
        }

        return null;
    }

    private function getAudioDuration($filepath)
    {
        // Используем getid3 библиотеку если доступна, иначе приблизительно
        if (function_exists('shell_exec')) {
            $output = shell_exec("ffprobe -i \"$filepath\" -show_entries format=duration -v quiet -of csv=\"p=0\"");
            if ($output) {
                return (float) trim($output);
            }
        }

        // Fallback: примерная длительность по размеру файла (очень приблизительно)
        $fileSize = filesize($filepath);
        return $fileSize / 32000; // Примерно для MP3 128kbps
    }

    private function getRelativeUrl($filepath)
    {
        // Для изображений используем относительный путь от текущей директории
        if (strpos($filepath, $this->imagesDir) === 0) {
            $relativePath = str_replace($this->imagesDir, '', $filepath);
            return 'images/' . $relativePath;
        }

        // Для аудио файлов используем стандартный путь
        return str_replace($_SERVER["DOCUMENT_ROOT"], '', $filepath);
    }

    private function successResponse($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        return json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
    }

    private function errorResponse($message)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        return json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    private function generateTimingsWithOpenAI($lyricsLines, $audioDuration)
    {
        if (!AIKaraokeConfig::isAPIKeyConfigured()) {
            return false;
        }

        $prompt = "Создай тайминги для караоке. Длительность песни: {$audioDuration} секунд. ";
        $prompt .= "Количество строк: " . count($lyricsLines) . ". ";
        $prompt .= "Строки песни:\n" . implode("\n", $lyricsLines) . "\n\n";
        $prompt .= "Верни JSON массив с таймингами в формате: [{\"start\": секунды, \"end\": секунды}, ...]. ";
        $prompt .= "Учитывай естественные паузы и ритм песни.";

        $data = [
            'model' => AIKaraokeConfig::GPT_MODEL,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Ты эксперт по музыке и караоке. Создаешь оптимальные тайминги для синхронизации текста с музыкой.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => AIKaraokeConfig::GPT_MAX_TOKENS,
            'temperature' => AIKaraokeConfig::GPT_TEMPERATURE
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

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $timingsJson = trim($result['choices'][0]['message']['content']);

                // Пытаемся извлечь JSON из ответа
                if (preg_match('/\[.*\]/', $timingsJson, $matches)) {
                    $timings = json_decode($matches[0], true);
                    if ($timings && count($timings) === count($lyricsLines)) {
                        return $timings;
                    }
                }
            }
        }

        return false;
    }

    private function generateEvenTimings($lyricsLines, $audioDuration)
    {
        $timings = [];
        $linesCount = count($lyricsLines);
        $timePerLine = $audioDuration / $linesCount;

        for ($i = 0; $i < $linesCount; $i++) {
            $start = $i * $timePerLine;
            $end = ($i + 1) * $timePerLine;

            $timings[] = [
                'start' => $start,
                'end' => $end
            ];
        }

        return $timings;
    }

    private function generatePlaceholderImage($line, $index)
    {
        // Создаем красивую заглушку с градиентом и текстом
        AIKaraokeConfig::debugLog('Generating placeholder image for slide ' . $index);

        // Создаем изображение 512x512
        $image = imagecreatetruecolor(512, 512);

        // Создаем градиент
        $colors = [
            [74, 144, 226],   // Blue
            [147, 51, 234],   // Purple
            [236, 72, 153],   // Pink
            [245, 101, 101],  // Red
            [251, 146, 60],   // Orange
            [34, 197, 94],    // Green
            [168, 85, 247],   // Violet
            [14, 165, 233]    // Sky blue
        ];

        $colorIndex = $index % count($colors);
        $color = $colors[$colorIndex];

        // Заливаем градиентом
        for ($y = 0; $y < 512; $y++) {
            $ratio = $y / 512;
            $r = $color[0] + ($ratio * (255 - $color[0]) * 0.3);
            $g = $color[1] + ($ratio * (255 - $color[1]) * 0.3);
            $b = $color[2] + ($ratio * (255 - $color[2]) * 0.3);

            $lineColor = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, 512, $y, $lineColor);
        }

        // Добавляем декоративные элементы вместо текста
        $white = imagecolorallocate($image, 255, 255, 255);
        $lightColor = imagecolorallocate($image,
            min(255, $color[0] + 50),
            min(255, $color[1] + 50),
            min(255, $color[2] + 50)
        );

        // Рисуем геометрические фигуры для создания абстрактного образа
        $centerX = 256;
        $centerY = 256;

        // Основной круг
        imagefilledellipse($image, $centerX, $centerY, 120, 120, $white);
        imagefilledellipse($image, $centerX, $centerY, 100, 100, $lightColor);

        // Дополнительные элементы в зависимости от индекса
        switch ($index % 5) {
            case 0:
                // Треугольники
                $points = array(
                    $centerX, $centerY - 40,
                    $centerX - 35, $centerY + 20,
                    $centerX + 35, $centerY + 20
                );
                imagefilledpolygon($image, $points, 3, $white);
                break;
            case 1:
                // Прямоугольники
                imagefilledrectangle($image, $centerX - 30, $centerY - 30, $centerX + 30, $centerY + 30, $white);
                break;
            case 2:
                // Ромб
                $points = array(
                    $centerX, $centerY - 40,
                    $centerX + 40, $centerY,
                    $centerX, $centerY + 40,
                    $centerX - 40, $centerY
                );
                imagefilledpolygon($image, $points, 4, $white);
                break;
            case 3:
                // Звезда
                for ($i = 0; $i < 8; $i++) {
                    $angle = $i * pi() / 4;
                    $x = $centerX + cos($angle) * 30;
                    $y = $centerY + sin($angle) * 30;
                    imagefilledellipse($image, $x, $y, 15, 15, $white);
                }
                break;
            case 4:
                // Волны
                for ($i = 0; $i < 3; $i++) {
                    $y = $centerY - 20 + $i * 20;
                    imageline($image, $centerX - 40, $y, $centerX + 40, $y, $white);
                }
                break;
        }

        // Добавляем номер слайда
        $slideNumber = $index + 1;
        imagestring($image, 5, $centerX - 10, $centerY + 50, "#$slideNumber", $white);

        // Сохраняем
        $filename = 'placeholder_' . $index . '_' . time() . '.png';
        $filepath = $this->imagesDir . $filename;

        if (imagepng($image, $filepath)) {
            imagedestroy($image);
            $relativeUrl = $this->getRelativeUrl($filepath);
            AIKaraokeConfig::debugLog('Placeholder image created: ' . $relativeUrl);
            return $relativeUrl;
        }

        imagedestroy($image);
        return null;
    }
}

// Обработка запроса
try {
    $generator = new KaraokeGenerator();
    echo $generator->processRequest();
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Критическая ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// НЕ подключаем footer для чистого JSON ответа
// require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
