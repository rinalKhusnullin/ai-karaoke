<?php

namespace Bitrix\AIKaraoke\Controller;

use Bitrix\Main\Request;

class Song extends \Bitrix\Main\Engine\Controller
{
	private $openaiApiKey;
	private $uploadDir;
//	public function __construct(Request $request = null)
//	{
//		parent::__construct($request);
//
//		$this->openaiApiKey = 'sk-ваш-реальный-api-ключ-openai-здесь';
//
//		$this->uploadDir = $_SERVER["DOCUMENT_ROOT"] . "/upload/aikaraoke/";
//
//		if (!is_dir($this->uploadDir)) {
//			mkdir($this->uploadDir, 0755, true);
//		}
//	}

	public function uploadAction(array $test)
	{
		$formData = [
			'minus_file' => $minusFile,
			'plus_file' => $plusFile,
			'lyrics' => $lyricsText,
		];

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return $this->errorResponse('Метод не поддерживается');
		}

		try {
			// Проверяем наличие файлов и текста
			if (!isset($formData['minus_file']) || !isset($formData['plus_file']) || empty($formData['lyrics'])) {
				return $this->errorResponse('Отсутствуют необходимые данные');
			}

			// Сохраняем загруженные файлы
			$minusPath = $this->saveUploadedFile($formData['minus_file'], 'minus');
			$plusPath = $this->saveUploadedFile($formData['plus_file'], 'plus');

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

		} catch (\Exception $e) {
			return $this->errorResponse('Внутренняя ошибка: ' . $e->getMessage());
		}
	}

	public function testAction(array $test): ?array
	{
		return $test;
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

		// Генерируем тайминги с помощью OpenAI
		$timings = $this->generateTimingsWithOpenAI($lyricsLines, $audioDuration);

		if (!$timings) {
			// Если ИИ недоступен, используем равномерное распределение
			$timings = $this->generateEvenTimings($lyricsLines, $audioDuration);
		}

		// Создаем слайды
		$slides = [];
		foreach ($lyricsLines as $index => $line) {
			$slides[] = [
				'text' => $line,
				'start' => $timings[$index]['start'],
				'end' => $timings[$index]['end']
			];
		}

		return [
			'slides' => $slides,
			'timeline' => $timings,
			'audio_url' => $this->getRelativeUrl($minusPath),
			'duration' => $audioDuration
		];
	}

	private function generateTimingsWithOpenAI($lyricsLines, $duration)
	{
		if (empty($this->openaiApiKey)) {
			return false;
		}

		$prompt = "Проанализируй текст песни и создай тайминги для караоке. Общая длительность песни: {$duration} секунд.\n\n";
		$prompt .= "Текст песни:\n" . implode("\n", $lyricsLines) . "\n\n";
		$prompt .= "Верни JSON массив с объектами, содержащими 'start' и 'end' в секундах для каждой строки. ";
		$prompt .= "Учти паузы между куплетами и припевами. Пример: [{\"start\": 0, \"end\": 3.5}, {\"start\": 4, \"end\": 7.2}]";

		$data = [
			'model' => 'gpt-4',
			'messages' => [
				[
					'role' => 'system',
					'content' => 'Ты эксперт по созданию караоке. Анализируй тексты песен и создавай точные тайминги.'
				],
				[
					'role' => 'user',
					'content' => $prompt
				]
			],
			'max_tokens' => 2000,
			'temperature' => 0.3
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->openaiApiKey
		]);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode === 200) {
			$result = json_decode($response, true);
			if (isset($result['choices'][0]['message']['content'])) {
				$content = $result['choices'][0]['message']['content'];
				// Извлекаем JSON из ответа
				preg_match('/\[.*\]/s', $content, $matches);
				if ($matches) {
					$timings = json_decode($matches[0], true);
					if ($timings && count($timings) === count($lyricsLines)) {
						return $timings;
					}
				}
			}
		}

		return false;
	}

	private function generateEvenTimings($lyricsLines, $duration)
	{
		$count = count($lyricsLines);
		$segmentDuration = $duration / $count;

		$timings = [];
		for ($i = 0; $i < $count; $i++) {
			$start = $i * $segmentDuration;
			$end = ($i + 1) * $segmentDuration;

			$timings[] = [
				'start' => round($start, 2),
				'end' => round($end, 2)
			];
		}

		return $timings;
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
		$this->addError(new \Bitrix\Main\Error($message));

		return null;
	}
}
