<?php

use Bitrix\Main\Result;
use Bitrix\Sign\Item\Member;
use Bitrix\Sign\Item\MemberCollection;
use Bitrix\Sign\Service\Container;
use Bitrix\Sign\Type\Member\Role;

\Bitrix\Main\UI\Extension::load('aikaraoke.karaoke-hub');

?>
	<div class="sign-ai-karaoke">
		<div class="sign-ai-karaoke__title">�� ������� ���������</div>
		<div class="sign-ai-karaoke__subtitle">���������� ������� � ������������������� ��������</div>
		<div class="sign-ai-karaoke__description">
			��������� ���������, �������� � ����� �����. ��� �� ������������� �������
			������������������ ������ ��� ���������� �������
		</div>

		<div class="sign-ai-karaoke__controls">
			<button class="ui-btn ui-btn-primary sign-ai-karaoke__button" id="upload-files-btn">
				��������� �����
			</button>
			<button class="ui-btn ui-btn-secondary sign-ai-karaoke__button" id="generate-karaoke-btn" disabled>
				������������ �������
			</button>
			<button class="ui-btn ui-btn-success sign-ai-karaoke__button" id="play-karaoke-btn" disabled>
				�������������
			</button>
		</div>

		<!-- ����� ������� -->
		<div class="karaoke-player" id="karaoke-player" style="display: none;">
			<div class="karaoke-player__header">
				<div class="karaoke-player__title">������� �����</div>
			</div>

			<div class="audio-controls" id="audio-controls">
				<div class="progress-bar" id="progress-bar">
					<div class="progress-fill" id="progress-fill"></div>
				</div>
				<div class="time-display" id="time-display">0:00 / 0:00</div>
			</div>

			<div class="slides-container" id="slides-container">
				<!-- ������ ����� ��������� ����������� -->
			</div>
		</div>
	</div>

	<!-- ��������� ���� ��� �������� ������ -->
	<div class="upload-modal" id="upload-modal">
		<div class="upload-modal__content">
			<div class="upload-modal__header">
				<div class="upload-modal__title">�������� ������ ��� �������</div>
				<button class="upload-modal__close" id="upload-modal-close">&times;</button>
			</div>

			<form id="upload-form">
				<div class="upload-form__group">
					<label class="upload-form__label" for="minus-file">
						��������� (����� ���� ��� ������):
					</label>
					<input type="file" class="upload-form__input" id="minus-file"
						   accept="audio/*" required>
				</div>

				<div class="upload-form__group">
					<label class="upload-form__label" for="plus-file">
						�������� (����� ���� � �������):
					</label>
					<input type="file" class="upload-form__input" id="plus-file"
						   accept="audio/*" required>
				</div>

				<div class="upload-form__group">
					<label class="upload-form__label" for="lyrics-text">
						����� �����:
					</label>
					<textarea class="upload-form__input upload-form__textarea"
							  id="lyrics-text" placeholder="������� ����� �����, ������ ������ �� ����� ������..." required></textarea>
				</div>

				<div class="upload-form__buttons">
					<button type="button" class="ui-btn ui-btn-light" id="cancel-upload">
						������
					</button>
					<button type="button" class="ui-btn ui-btn-primary" id="confirm-upload">
						��������� �����
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- ����������� ������� -->
	<div class="loading-overlay" id="loading-overlay">
		<div class="loading-content">
			<div class="loading-spinner"></div>
			<div class="loading-text">��������� �������...</div>
			<div class="loading-description">
				�� ����������� ����� � ������� ������������������ ������
			</div>
		</div>
	</div>

<script>
	BX.ready(() => {
		const karaokeHub = new BX.Aikaraoke.KaraokePlayer();
	});
</script>
