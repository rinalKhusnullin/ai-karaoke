<?php

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Web\Json;

// НЕ подключаем header и footer для чистого JSON ответа
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';

\Bitrix\Main\Loader::requireModule('sign');
\Bitrix\Main\Loader::requireModule('aikaraoke');

class KaraokeGenerator
{
    private $uploadDir;
    private $imagesDir;
    private $audioProcessAPI;

    public function __construct()
    {
        // Используем централизованную конфигурацию
        $this->uploadDir = AIKaraokeConfig::getUploadDir();
        $this->imagesDir = AIKaraokeConfig::getImagesDir();

        // URL вашего API для обработки аудио
        $this->audioProcessAPI = 'http://212.113.116.182:8080/api/process';

        // Создаем директории через конфигурацию
        $directories = AIKaraokeConfig::createDirectories();

        AIKaraokeConfig::debugLog('Karaoke Generator initialized', [
            'api_key_configured' => AIKaraokeConfig::isAPIKeyConfigured(),
            'audio_api' => $this->audioProcessAPI,
            'directories' => $directories,
        ]);
    }

    public function processRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->errorResponse('Метод не поддерживается');
        }

		global $USER;
	    if (!$USER->IsAuthorized()) {
		    return $this->errorResponse('Требуется авторизация');
	    }
	    $userId = $USER->GetID();

        try {
            // Проверяем наличие файла
            if (!isset($_FILES['plus_file'])) {
                return $this->errorResponse('Отсутствует аудио файл');
            }

            // Сохраняем загруженный файл
            $audioPath = $this->saveUploadedFile($_FILES['plus_file'], 'audio');

            if (!$audioPath) {
                return $this->errorResponse('Ошибка сохранения файла');
            }

            AIKaraokeConfig::debugLog('Audio file saved: ' . $audioPath);

            // Отправляем файл на ваш API для обработки
            $apiResponse = $this->processAudioWithAPI($audioPath);

            if (!$apiResponse) {
                return $this->errorResponse('Ошибка обработки аудио через API');
            }

            AIKaraokeConfig::debugLog('API response received', $apiResponse);

            // Генерируем караоке на основе ответа API
            $karaokeData = $this->generateKaraokeFromAPIResponse($apiResponse);

            if (!$karaokeData) {
                return $this->errorResponse('Ошибка генерации караоке');
            }

			// Сохраняем информацию о песне в БД
	        $duration = $karaokeData['duration'];

            return $this->successResponse($karaokeData);

        } catch (Exception $e) {
            AIKaraokeConfig::debugLog('Exception in processRequest: ' . $e->getMessage());
            return $this->errorResponse('Внутренняя ошибка: ' . $e->getMessage());
        }
    }

    private function saveUploadedFile($file, $prefix)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            AIKaraokeConfig::debugLog('Upload error: ' . $file['error']);
            return false;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $this->uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            AIKaraokeConfig::debugLog('File uploaded successfully: ' . $filepath);
            return $filepath;
        }

        AIKaraokeConfig::debugLog('Failed to move uploaded file');
        return false;
    }

    private function processAudioWithAPI($audioPath)
    {
        AIKaraokeConfig::debugLog('Sending audio to API: ' . $this->audioProcessAPI);

        try {
            // Создаем CURLFile для отправки файла
            $cFile = new CURLFile($audioPath, mime_content_type($audioPath), basename($audioPath));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->audioProcessAPI);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['audio' => $cFile]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 минут таймаут
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            AIKaraokeConfig::debugLog('API response code: ' . $httpCode);

            if (!empty($curlError)) {
                AIKaraokeConfig::debugLog('CURL error: ' . $curlError);
                return false;
            }

            if ($httpCode !== 200) {
                AIKaraokeConfig::debugLog('API returned non-200 status: ' . $httpCode . ', Response: ' . $response);
                return false;
            }

            // Парсим JSON ответ
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                AIKaraokeConfig::debugLog('JSON decode error: ' . json_last_error_msg() . ', Raw response: ' . $response);
                return false;
            }

            AIKaraokeConfig::debugLog('API response parsed successfully');
            return $data;

        } catch (Exception $e) {
            AIKaraokeConfig::debugLog('Exception in processAudioWithAPI: ' . $e->getMessage());
            return false;
        }
    }

    private function generateKaraokeFromAPIResponse($apiResponse)
    {
        AIKaraokeConfig::debugLog('Generating karaoke from API response');

        // API возвращает:
        // - transcript_chunks: массив чанков с текстом и таймкодами
        // - accompaniment_url: URL минусовки
        // - transcript: полный текст песни
        // - success: статус обработки

        if (!isset($apiResponse['transcript_chunks']) || !isset($apiResponse['accompaniment_url'])) {
            AIKaraokeConfig::debugLog('Invalid API response structure', $apiResponse);
            return false;
        }

        if (!$apiResponse['success']) {
            AIKaraokeConfig::debugLog('API processing failed');
            return false;
        }

        $transcriptChunks = $apiResponse['transcript_chunks'];
        $accompanimentUrl = $apiResponse['accompaniment_url'];

        AIKaraokeConfig::debugLog('Processing transcript chunks', ['count' => count($transcriptChunks)]);

        // Сохраняем минусовку локально
        $localMinusUrl = $this->saveMinusAudio($accompanimentUrl);
        if (!$localMinusUrl) {
            AIKaraokeConfig::debugLog('Failed to save minus audio');
            return false;
        }

        // Группируем строки в слайды из transcript_chunks
        $slides = $this->createSlidesFromTranscriptChunks($transcriptChunks);

        // Генерируем изображения для слайдов
        $this->generateImagesForSlides($slides);

        // Получаем длительность аудио
        $audioDuration = $this->getAudioDurationFromSlides($slides);

        AIKaraokeConfig::debugLog('Karaoke generation completed', [
            'slides_count' => count($slides),
            'duration' => $audioDuration,
        ]);

        return [
            'slides' => $slides,
            'timeline' => $this->extractTimeline($slides),
            'audio_url' => $localMinusUrl,
            'duration' => $audioDuration,
            'track_id' => $apiResponse['track_id'] ?? null, // Передаем track_id для анализа
        ];
    }

    private function saveMinusAudio($minusAudioUrl)
    {
        AIKaraokeConfig::debugLog('Saving minus audio');

        try {
            // Проверяем, это URL или base64
            if (filter_var($minusAudioUrl, FILTER_VALIDATE_URL)) {
                // Это URL, скачиваем файл
                $audioData = file_get_contents($minusAudioUrl);
                if ($audioData === false) {
                    AIKaraokeConfig::debugLog('Failed to download minus audio from URL: ' . $minusAudioUrl);
                    return false;
                }
            } else {
                // Предполагаем, что это base64
                $audioData = base64_decode($minusAudioUrl);
                if ($audioData === false) {
                    AIKaraokeConfig::debugLog('Failed to decode base64 audio data');
                    return false;
                }
            }

            // Сохраняем файл
            $filename = 'minus_' . time() . '_' . rand(1000, 9999) . '.mp3';
            $filepath = $this->uploadDir . $filename;

            if (file_put_contents($filepath, $audioData)) {
                $relativeUrl = $this->getRelativeUrl($filepath);
                AIKaraokeConfig::debugLog('Minus audio saved: ' . $relativeUrl);
                return $relativeUrl;
            } else {
                AIKaraokeConfig::debugLog('Failed to save minus audio file');
                return false;
            }

        } catch (Exception $e) {
            AIKaraokeConfig::debugLog('Exception in saveMinusAudio: ' . $e->getMessage());
            return false;
        }
    }

    private function createSlidesFromLyrics($lyrics)
    {
        AIKaraokeConfig::debugLog('Creating slides from lyrics');

        $slides = [];
        $linesPerSlide = AIKaraokeConfig::LINES_PER_SLIDE;
        $currentSlideLines = [];

        foreach ($lyrics as $line) {
            // Ожидаем структуру: {text: "текст", start: секунды, end: секунды}
            if (!isset($line['text']) || !isset($line['start']) || !isset($line['end'])) {
                AIKaraokeConfig::debugLog('Invalid line structure', $line);
                continue;
            }

            $currentSlideLines[] = $line;

            // Когда набралось нужное количество строк, создаем слайд
            if (count($currentSlideLines) >= $linesPerSlide) {
                $slides[] = $this->createSlideFromLines($currentSlideLines);
                $currentSlideLines = [];
            }
        }

        // Добавляем оставшиеся строки, если есть
        if (!empty($currentSlideLines)) {
            $slides[] = $this->createSlideFromLines($currentSlideLines);
        }

        AIKaraokeConfig::debugLog('Slides created', ['count' => count($slides)]);
        return $slides;
    }

    private function createSlidesFromTranscriptChunks($transcriptChunks)
    {
        AIKaraokeConfig::debugLog('Creating slides from transcript chunks');

        $slides = [];
        $linesPerSlide = AIKaraokeConfig::LINES_PER_SLIDE;
        $currentSlideLines = [];

        foreach ($transcriptChunks as $chunk) {
            // API возвращает структуру: {text: "текст", start: секунды, end: секунды, word_count: число}
            if (!isset($chunk['text']) || !isset($chunk['start']) || !isset($chunk['end'])) {
                AIKaraokeConfig::debugLog('Invalid chunk structure', $chunk);
                continue;
            }

            $currentSlideLines[] = $chunk;

            // Когда набралось нужное количество строк, создаем слайд
            if (count($currentSlideLines) >= $linesPerSlide) {
                $slides[] = $this->createSlideFromLines($currentSlideLines);
                $currentSlideLines = [];
            }
        }

        // Добавляем оставшиеся строки, если есть
        if (!empty($currentSlideLines)) {
            $slides[] = $this->createSlideFromLines($currentSlideLines);
        }

        AIKaraokeConfig::debugLog('Slides created', ['count' => count($slides)]);
        return $slides;
    }

    private function createSlideFromLines($lines)
    {
        $texts = [];
        $minStart = PHP_FLOAT_MAX;
        $maxEnd = 0;

        foreach ($lines as $line) {
            $texts[] = $line['text'];
            $minStart = min($minStart, $line['start']);
            $maxEnd = max($maxEnd, $line['end']);
        }

        return [
            'text' => implode("\n", $texts),
            'start' => $minStart,
            'end' => $maxEnd,
            'image' => null, // Будет заполнено позже
        ];
    }

    private function extractTimeline($slides)
    {
        $timeline = [];
        foreach ($slides as $slide) {
            $timeline[] = [
                'start' => $slide['start'],
                'end' => $slide['end'],
            ];
        }
        return $timeline;
    }

    private function getAudioDurationFromSlides($slides)
    {
        if (empty($slides)) {
            return 0;
        }

        $lastSlide = end($slides);
        return $lastSlide['end'];
    }

    private function generateImagesForSlides(&$slides)
    {
        AIKaraokeConfig::debugLog('Starting image generation for ' . count($slides) . ' slides');

        if (!AIKaraokeConfig::isAPIKeyConfigured()) {
            AIKaraokeConfig::debugLog('No OpenAI API key configured, skipping image generation');
            return [];
        }

        // Собираем весь текст для анализа темы
        $fullLyrics = '';
        foreach ($slides as $slide) {
            $fullLyrics .= $slide['text'] . ' ';
        }

        // Генерируем общую тему песни для контекста
        $songTheme = $this->analyzeSongTheme($fullLyrics);
        AIKaraokeConfig::debugLog('Song theme detected: ' . $songTheme);

        foreach ($slides as $index => &$slide) {
            AIKaraokeConfig::debugLog('Generating image for slide ' . $index . ': "' . $slide['text'] . '"');

            $imageUrl = $this->generateImageForLine($slide['text'], $songTheme, $index);
            if ($imageUrl) {
                $slide['image'] = $imageUrl;
                AIKaraokeConfig::debugLog('Image generated successfully for slide ' . $index . ': ' . $imageUrl);
            } else {
                AIKaraokeConfig::debugLog('Failed to generate image for slide ' . $index);
                $slide['image'] = null;
            }

            // Используем задержку из конфигурации
            usleep(AIKaraokeConfig::API_REQUEST_DELAY);
        }

        AIKaraokeConfig::debugLog('Image generation completed. Generated images for slides.');
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
                    'content' => 'You are an expert in analyzing musical texts. Determine theme and mood for creating visual images. Answer briefly in English, maximum 50 words.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => 100,
            'temperature' => AIKaraokeConfig::GPT_TEMPERATURE,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AIKaraokeConfig::getOpenAIKey(),
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
            'style' => AIKaraokeConfig::DALLE_STYLE,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/images/generations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AIKaraokeConfig::getOpenAIKey(),
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
            'музыкальная атмосфера' => 'musical atmosphere',
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
                    'user_agent' => 'Mozilla/5.0 (compatible; AI Karaoke Generator)',
                ],
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
            [14, 165, 233],    // Sky blue
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
                    $centerX + 35, $centerY + 20,
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
                    $centerX - 40, $centerY,
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

	private function saveSongVersions($songId, $minusPath, $plusPath, $lyricsPath = null)
	{
		$versions = [
			[
				'SONG_ID' => $songId,
				'VERSION_TYPE' => 'original',
				'STORAGE_PATH' => $minusPath,
			],
			[
				'SONG_ID' => $songId,
				'VERSION_TYPE' => 'instrumental',
				'STORAGE_PATH' => $minusPath,
			],
			[
				'SONG_ID' => $songId,
				'VERSION_TYPE' => 'vocals',
				'STORAGE_PATH' => $plusPath,
			],
		];

		if ($lyricsPath) {
			$versions[] = [
				'SONG_ID' => $songId,
				'VERSION_TYPE' => 'lyrics',
				'STORAGE_PATH' => $lyricsPath,
				'METADATA' => json_encode(['format' => 'json']),
			];
		}

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
