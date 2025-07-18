<?php

use Bitrix\Main\Result;
use Bitrix\Sign\Item\Member;
use Bitrix\Sign\Item\MemberCollection;
use Bitrix\Sign\Service\Container;
use Bitrix\Sign\Type\Member\Role;

\Bitrix\Main\UI\Extension::load('aikaraoke.karaoke-hub');

?>
<div class="sign-ai-karaoke">
	<div class="sign-ai-karaoke__title">ИИ Караоке Генератор</div>
	<div class="sign-ai-karaoke__subtitle">Создавайте караоке с синхронизированными слайдами</div>
	<div class="sign-ai-karaoke__description">
		Загрузите минусовку, плюсовку и текст песни. Наш ИИ автоматически создаст
		синхронизированные слайды для идеального караоке
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
			<div class="upload-modal__title">Загрузка файлов для караоке</div>
			<button class="upload-modal__close" id="upload-modal-close">&times;</button>
		</div>

		<form id="upload-form">
			<div class="upload-form__group">
				<label class="upload-form__label" for="minus-file">
					Минусовка (аудио файл без вокала):
				</label>
				<input type="file" class="upload-form__input" id="minus-file"
					   accept="audio/*" required>
			</div>

			<div class="upload-form__group">
				<label class="upload-form__label" for="plus-file">
					Плюсовка (аудио файл с вокалом):
				</label>
				<input type="file" class="upload-form__input" id="plus-file"
					   accept="audio/*" required>
			</div>

			<div class="upload-form__group">
				<label class="upload-form__label" for="lyrics-text">
					Текст песни:
				</label>
				<textarea class="upload-form__input upload-form__textarea"
						  id="lyrics-text" placeholder="Введите текст песни, каждая строка на новой строке..." required></textarea>
			</div>

			<div class="upload-form__buttons">
				<button type="button" class="ui-btn ui-btn-light" id="cancel-upload">
					Отмена
				</button>
				<button type="button" class="ui-btn ui-btn-primary" id="confirm-upload">
					Загрузить файлы
				</button>
			</div>
		</form>
	</div>
</div>

<!-- Загрузочный оверлей -->
<div class="loading-overlay" id="loading-overlay">
	<div class="loading-content">
		<div class="loading-spinner"></div>
		<div class="loading-text">Генерация караоке...</div>
		<div class="loading-description">
			ИИ анализирует аудио и создает синхронизированные слайды
		</div>
	</div>
</div>

<script>
	BX.ready(() => {
		const karaokeHub = new BX.Aikaraoke.KaraokePlayer();
	});
</script>
