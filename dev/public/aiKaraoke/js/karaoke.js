class KaraokePlayer {
    constructor() {
        this.audioContext = null;
        this.currentSlide = 0;
        this.slides = [];
        this.isPlaying = false;
        this.audioElement = null;
        this.timeline = [];
        this.filesUploaded = false;

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initAudioContext();
    }

    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            const uploadBtn = document.getElementById('upload-files-btn');
            const generateBtn = document.getElementById('generate-karaoke-btn');
            const playBtn = document.getElementById('play-karaoke-btn');
            const confirmUploadBtn = document.getElementById('confirm-upload');
            const cancelUploadBtn = document.getElementById('cancel-upload');
            const modalCloseBtn = document.getElementById('upload-modal-close');

            if (uploadBtn) {
                uploadBtn.addEventListener('click', () => this.showUploadModal());
            }

            if (generateBtn) {
                generateBtn.addEventListener('click', () => this.generateKaraoke());
            }

            if (playBtn) {
                playBtn.addEventListener('click', () => this.togglePlayback());
            }

            if (confirmUploadBtn) {
                confirmUploadBtn.addEventListener('click', () => this.confirmUpload());
            }

            if (cancelUploadBtn) {
                cancelUploadBtn.addEventListener('click', () => this.hideUploadModal());
            }

            if (modalCloseBtn) {
                modalCloseBtn.addEventListener('click', () => this.hideUploadModal());
            }

            // Закрытие модального окна по клику вне его
            const modal = document.getElementById('upload-modal');
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        this.hideUploadModal();
                    }
                });
            }

            // Обработка изменения файлов
            ['minus-file', 'plus-file', 'lyrics-text'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('change', () => this.validateUploadForm());
                    element.addEventListener('input', () => this.validateUploadForm());
                }
            });
        });
    }

    initAudioContext() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (error) {
            console.error('Аудио контекст не поддерживается:', error);
        }
    }

    showUploadModal() {
        const modal = document.getElementById('upload-modal');
        if (modal) {
            modal.style.display = 'block';
            this.validateUploadForm();
        }
    }

    hideUploadModal() {
        const modal = document.getElementById('upload-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    validateUploadForm() {
        const minusFile = document.getElementById('minus-file').files[0];
        const plusFile = document.getElementById('plus-file').files[0];
        const lyricsText = document.getElementById('lyrics-text').value.trim();
        const confirmBtn = document.getElementById('confirm-upload');

        const isValid = minusFile && plusFile && lyricsText;

        if (confirmBtn) {
            confirmBtn.disabled = !isValid;
        }

        return isValid;
    }

    confirmUpload() {
        if (this.validateUploadForm()) {
            this.filesUploaded = true;
            document.getElementById('generate-karaoke-btn').disabled = false;
            this.hideUploadModal();

            // Показываем информацию о загруженных файлах
            this.showUploadedFilesInfo();
        }
    }

    showUploadedFilesInfo() {
        const minusFile = document.getElementById('minus-file').files[0];
        const plusFile = document.getElementById('plus-file').files[0];

        // Можно добавить уведомление о успешной загрузке
        console.log('Файлы готовы к обработке:', {
            minus: minusFile.name,
            plus: plusFile.name
        });
    }

    async generateKaraoke() {
        const formData = new FormData();
        const minusFile = document.getElementById('minus-file').files[0];
        const plusFile = document.getElementById('plus-file').files[0];
        const lyricsText = document.getElementById('lyrics-text').value;

        if (!minusFile || !plusFile || !lyricsText.trim()) {
            alert('Пожалуйста, сначала загрузите все файлы через кнопку "Загрузить файлы"');
            return;
        }

        formData.append('minus_file', minusFile);
        formData.append('plus_file', plusFile);
        formData.append('lyrics', lyricsText);

        try {
            this.showLoading(true);

            const response = await fetch('generate_karaoke.php', {
                method: 'POST',
                body: formData
            });

            // Проверяем статус ответа
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Получаем текст ответа для отладки
            const responseText = await response.text();
            console.log('Ответ сервера:', responseText);

            // Пытаемся распарсить JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Ошибка парсинга JSON:', parseError);
                console.error('Полученный текст:', responseText);
                throw new Error('Сервер вернул некорректный JSON ответ. Проверьте консоль браузера для деталей.');
            }

            if (result.success) {
                this.slides = result.slides;
                this.timeline = result.timeline;
                this.displaySlides();
                this.showKaraokePlayer();

                // Создаем аудио элемент для воспроизведения
                this.createAudioElement(result.audio_url);

                // Активируем кнопку воспроизведения
                document.getElementById('play-karaoke-btn').disabled = false;
            } else {
                alert('Ошибка генерации: ' + result.error);
            }
        } catch (error) {
            console.error('Ошибка запроса:', error);
            alert('Произошла ошибка при генерации караоке: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }

    showKaraokePlayer() {
        const player = document.getElementById('karaoke-player');
        if (player) {
            player.style.display = 'block';
            player.scrollIntoView({ behavior: 'smooth' });
        }
    }

    createAudioElement(audioUrl) {
        if (this.audioElement) {
            this.audioElement.remove();
        }

        this.audioElement = document.createElement('audio');
        this.audioElement.src = audioUrl;
        this.audioElement.addEventListener('timeupdate', () => this.updateSlides());
        this.audioElement.addEventListener('ended', () => this.onAudioEnded());
        this.audioElement.addEventListener('loadedmetadata', () => this.updateTimeDisplay());

        // Добавляем прогресс бар
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.addEventListener('click', (e) => this.seekAudio(e));
        }

        document.body.appendChild(this.audioElement);
    }

    seekAudio(e) {
        if (!this.audioElement) return;

        const progressBar = e.currentTarget;
        const rect = progressBar.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percentage = clickX / rect.width;
        const newTime = percentage * this.audioElement.duration;

        this.audioElement.currentTime = newTime;
    }

    updateTimeDisplay() {
        if (!this.audioElement) return;

        const timeDisplay = document.getElementById('time-display');
        const progressFill = document.getElementById('progress-fill');

        if (timeDisplay) {
            const current = this.formatTime(this.audioElement.currentTime);
            const total = this.formatTime(this.audioElement.duration || 0);
            timeDisplay.textContent = `${current} / ${total}`;
        }

        if (progressFill && this.audioElement.duration) {
            const percentage = (this.audioElement.currentTime / this.audioElement.duration) * 100;
            progressFill.style.width = `${percentage}%`;
        }
    }

    displaySlides() {
        const container = document.getElementById('slides-container');
        if (!container) return;

        container.innerHTML = '';

        this.slides.forEach((slide, index) => {
            const slideElement = document.createElement('div');
            slideElement.className = 'karaoke-slide';
            slideElement.id = `slide-${index}`;
            slideElement.innerHTML = `
                <div class="slide-text">${slide.text}</div>
                <div class="slide-timing">${this.formatTime(slide.start)} - ${this.formatTime(slide.end)}</div>
            `;

            // Добавляем возможность перехода к слайду по клику
            slideElement.addEventListener('click', () => {
                if (this.audioElement) {
                    this.audioElement.currentTime = slide.start;
                }
            });

            container.appendChild(slideElement);
        });
    }

    updateSlides() {
        if (!this.audioElement || !this.slides.length) return;

        const currentTime = this.audioElement.currentTime;

        // Обновляем время
        this.updateTimeDisplay();

        // Находим текущий слайд
        const newSlide = this.slides.findIndex(slide =>
            currentTime >= slide.start && currentTime <= slide.end
        );

        if (newSlide !== -1 && newSlide !== this.currentSlide) {
            // Убираем активный класс с предыдущего слайда
            const prevSlide = document.getElementById(`slide-${this.currentSlide}`);
            if (prevSlide) {
                prevSlide.classList.remove('active');
            }

            // Добавляем активный класс текущему слайду
            const currentSlideElement = document.getElementById(`slide-${newSlide}`);
            if (currentSlideElement) {
                currentSlideElement.classList.add('active');
                currentSlideElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            this.currentSlide = newSlide;
        }
    }

    togglePlayback() {
        if (!this.audioElement) {
            alert('Сначала сгенерируйте караоке');
            return;
        }

        if (this.isPlaying) {
            this.audioElement.pause();
            this.isPlaying = false;
            document.getElementById('play-karaoke-btn').textContent = 'Воспроизвести';
        } else {
            this.audioElement.play();
            this.isPlaying = true;
            document.getElementById('play-karaoke-btn').textContent = 'Пауза';
        }
    }

    onAudioEnded() {
        this.isPlaying = false;
        this.currentSlide = 0;
        document.getElementById('play-karaoke-btn').textContent = 'Воспроизвести';

        // Убираем активные классы со всех слайдов
        document.querySelectorAll('.karaoke-slide.active').forEach(slide => {
            slide.classList.remove('active');
        });
    }

    showLoading(show) {
        const loader = document.getElementById('loading-overlay');
        if (loader) {
            loader.style.display = show ? 'block' : 'none';
        }
    }

    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
}

// Инициализация при загрузке страницы
const karaokePlayer = new KaraokePlayer();
