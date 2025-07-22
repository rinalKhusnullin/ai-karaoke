<?php

namespace Bitrix\AIKaraoke\Controller;

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\Request;
use CBlog;
use CBlogPost;

class Song extends \Bitrix\Main\Engine\Controller
{
	private $openaiApiKey;
	private $uploadDir;
	public function __construct(Request $request = null)
	{
		parent::__construct($request);

		$this->openaiApiKey = 'sk-ваш-реальный-api-ключ-openai-здесь';

		$this->uploadDir = $_SERVER["DOCUMENT_ROOT"] . "/upload/aikaraoke/";

		if (!is_dir($this->uploadDir)) {
			mkdir($this->uploadDir, 0755, true);
		}
	}

	public function uploadAction(array  $formData): array
	{

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return $this->errorResponse('Метод не поддерживается');
		}
		$formData['minus_file'] = $formData['minusFile'];
		$formData['plus_file'] = $formData['plusFile'];
		$formData['lyrics'] = $formData['lyricsText'];

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

	public function postMessageAction($text, $score): void
	{
		$userId = CurrentUser::get()?->getId();
		if (!$userId)
		{
			return;
		}

		Loader::includeModule("blog");
		Loader::includeModule("socialnetwork");

		$arBlog = CBlog::GetByOwnerID($userId);
		$arFields= array(
			"TITLE" => "Заголовок",
			"DETAIL_TEXT" => "Описание",
			"DATE_PUBLISH" => date('d.m.Y H:i:s'),
			"PUBLISH_STATUS" => "P",
			"CATEGORY_ID" => "",
			"PATH" => "/company/personal/user/1/blog/#post_id#/",
			"URL" => "admin-blog-s1",
			"PERMS_POST" => [],
			"PERMS_COMMENT" => [],
			"SOCNET_RIGHTS" => [
				"UA", "G2"
			],
			"=DATE_CREATE" => "now()",
			"AUTHOR_ID" => $this,
			"BLOG_ID" => $arBlog['ID'],
		);

		$newID= CBlogPost::Add($arFields);

		$arFields["ID"] = $newID;
		$arParamsNotify = Array(
			"bSoNet" => true,
			"UserID" => $userId,
			"user_id" => $userId,
		);

		CBlogPost::Notify($arFields, array(), $arParamsNotify);
	}
}
