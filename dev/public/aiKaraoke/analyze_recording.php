<?php

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Web\Json;

// НЕ подключаем header и footer для чистого JSON ответа
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';

\Bitrix\Main\Loader::requireModule('sign');

class RecordingAnalyzer
{
    private $analyzeAPI;

    public function __construct()
    {
        // URL API для анализа записи
        $this->analyzeAPI = 'http://212.113.116.182:8080/api/compare_vocals';

        AIKaraokeConfig::debugLog('Recording Analyzer initialized', [
            'analyze_api' => $this->analyzeAPI
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

        try {
            // Проверяем наличие необходимых данных
            if (!isset($_FILES['vocal_track']) || !isset($_POST['track_id'])) {
                return $this->errorResponse('Отсутствуют необходимые данные для анализа');
            }

            $vocalFile = $_FILES['vocal_track'];
            $trackId = $_POST['track_id'];

            AIKaraokeConfig::debugLog('Starting vocal analysis', [
                'track_id' => $trackId,
                'file_size' => $vocalFile['size'],
                'file_type' => $vocalFile['type']
            ]);

            // Отправляем запрос на анализ
            $analysisResult = $this->analyzeVocalWithAPI($vocalFile, $trackId);

            if (!$analysisResult) {
                return $this->errorResponse('Ошибка анализа записи через API');
            }

            AIKaraokeConfig::debugLog('Analysis completed successfully', $analysisResult);

            return $this->successResponse($analysisResult);

        } catch (Exception $e) {
            AIKaraokeConfig::debugLog('Exception in processRequest: ' . $e->getMessage());
            return $this->errorResponse('Внутренняя ошибка: ' . $e->getMessage());
        }
    }

    private function analyzeVocalWithAPI($vocalFile, $trackId)
    {
        AIKaraokeConfig::debugLog('Sending vocal to analysis API: ' . $this->analyzeAPI);

        try {
            // Создаем CURLFile для отправки файла
            $cFile = new CURLFile($vocalFile['tmp_name'], $vocalFile['type'], $vocalFile['name']);

            $postData = [
                'vocal_track' => $cFile,
                'track_id' => $trackId
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->analyzeAPI);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 минут таймаут
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            AIKaraokeConfig::debugLog('Analysis API response code: ' . $httpCode);

            if (!empty($curlError)) {
                AIKaraokeConfig::debugLog('CURL error: ' . $curlError);
                return false;
            }

            if ($httpCode !== 200) {
                AIKaraokeConfig::debugLog('Analysis API returned non-200 status: ' . $httpCode . ', Response: ' . $response);
                return false;
            }

            // Парсим JSON ответ
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                AIKaraokeConfig::debugLog('JSON decode error: ' . json_last_error_msg() . ', Raw response: ' . $response);
                return false;
            }

            AIKaraokeConfig::debugLog('Analysis API response parsed successfully');

            // Проверяем успешность анализа
            if (!isset($data['success']) || !$data['success']) {
                AIKaraokeConfig::debugLog('Analysis failed according to API response', $data);
                return false;
            }

            return $data;

        } catch (Exception $e) {
            AIKaraokeConfig::debugLog('Exception in analyzeVocalWithAPI: ' . $e->getMessage());
            return false;
        }
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
}

// Обработка запроса
try {
    $analyzer = new RecordingAnalyzer();
    echo $analyzer->processRequest();
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Критическая ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// НЕ подключаем footer для чистого JSON ответа
?>
