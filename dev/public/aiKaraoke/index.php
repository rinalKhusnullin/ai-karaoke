<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

\Bitrix\Main\Loader::requireModule('sign');
\Bitrix\Main\UI\Extension::load("ui.btn");

// Добавляем стили и скрипты
$APPLICATION->AddHeadString('<link rel="stylesheet" href="css/karaoke.css">');
$APPLICATION->AddHeadString('<script src="js/karaoke.js" defer></script>');
$APPLICATION->SetTitle('Караоке генератор');

?>
	<div class="sign-ai-karaoke">
		<div class="sign-ai-karaoke__title">Караоке Генератор</div>
		<div class="sign-ai-karaoke__subtitle">Создавайте караоке с синхронизированными слайдами</div>
		<div class="sign-ai-karaoke__description">
			Загрузите аудио файл с голосом. Наш ИИ автоматически распознает текст,
			создаст минусовку и синхронизированные слайды с уникальными изображениями для идеального караоке
		</div>

		<div class="sign-ai-karaoke__controls">
			<button class="ui-btn ui-btn-primary sign-ai-karaoke__button" id="upload-files-btn">
				Загрузить файлы
			</button>
			<button class="ui-btn ui-btn-secondary sign-ai-karaoke__button" id="generate-karaoke-btn" disabled>
				Генерировать караоке
			</button>
			<button class="ui-btn ui-btn-success sign-ai-karaoke__button" id="play-karaoke-btn" disabled>
				Воспроизвести
			</button>
		</div>

		<!-- Плеер караоке -->
		<div class="karaoke-player" id="karaoke-player" style="display: none;">
			<div class="karaoke-player__header">
				<div class="karaoke-player__title">Караоке плеер</div>
			</div>

			<div class="audio-controls" id="audio-controls">
				<div class="progress-bar" id="progress-bar">
					<div class="progress-fill" id="progress-fill"></div>
				</div>
				<div class="time-display" id="time-display">0:00 / 0:00</div>
			</div>

			<div class="slides-container" id="slides-container">
				<!-- Слайды будут добавлены динамически -->
			</div>
		</div>
	</div>

	<!-- Модальное окно для загрузки файлов -->
	<div class="upload-modal" id="upload-modal">
		<div class="upload-modal__content">
			<div class="upload-modal__header">
				<div class="upload-modal__title">Загрузка файла для караоке</div>
				<button class="upload-modal__close" id="upload-modal-close">&times;</button>
			</div>

			<form id="upload-form">
				<div class="upload-form__group">
					<label class="upload-form__label" for="plus-file">
						Аудио файл с голосом (плюсовка):
					</label>
					<input type="file" class="upload-form__input" id="plus-file"
						   accept="audio/*" required>
					<small style="color: #666; font-size: 0.85rem; margin-top: 5px; display: block;">
						Наш ИИ автоматически распознает текст и создаст минусовку
					</small>
				</div>

				<div class="upload-form__buttons">
					<button type="button" class="ui-btn ui-btn-light" id="cancel-upload">
						Отмена
					</button>
					<button type="button" class="ui-btn ui-btn-primary" id="confirm-upload">
						Загрузить файл
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Загрузочный оверлей -->
	<div class="loading-overlay" id="loading-overlay">
		<div class="loading-content">
			<div class="loading-spinner"></div>
			<div class="loading-text">Генерация караоке с ИИ-изображениями...</div>
			<div class="loading-description">
				ИИ анализирует аудио, создает синхронизированные слайды и генерирует уникальные изображения для каждой строки
			</div>
		</div>
	</div>

<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
