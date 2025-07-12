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

            // Обработка изменения файлов - убираем lyrics-text, так как теперь API сам распознает текст
            const plusFileElement = document.getElementById('plus-file');
            if (plusFileElement) {
                plusFileElement.addEventListener('change', () => this.validateUploadForm());
            }
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
        const plusFileElement = document.getElementById('plus-file');
        const confirmBtn = document.getElementById('confirm-upload');

        // Проверяем существование элемента перед обращением к его свойствам
        if (!plusFileElement) {
            console.warn('Element plus-file not found');
            if (confirmBtn) {
                confirmBtn.disabled = true;
            }
            return false;
        }

        const plusFile = plusFileElement.files[0];

        // Теперь нужен только файл плюсовки - API сам сделает расшифровку и минус
        const isValid = plusFile;

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

            // Показываем информацию о загруженном файле
            this.showUploadedFilesInfo();
        }
    }

    showUploadedFilesInfo() {
        const plusFileElement = document.getElementById('plus-file');

        // Проверяем существование элемента перед обращением к его свойствам
        if (!plusFileElement || !plusFileElement.files[0]) {
            console.warn('Element plus-file not found or no file selected');
            return;
        }

        const plusFile = plusFileElement.files[0];

        // Показываем уведомление о готовности
        console.log('Файл готов к обработке:', {
            plus: plusFile.name
        });

        // Показываем уведомление пользователю
        this.showNotification('✅ Файл загружен! Теперь нажмите "Генерировать караоке" для автоматической обработки.');
    }

    async generateKaraoke() {
        const formData = new FormData();
        const plusFile = document.getElementById('plus-file').files[0];

        if (!plusFile) {
            alert('Пожалуйста, сначала загрузите аудио файл через кнопку "Загрузить файлы"');
            return;
        }

        formData.append('plus_file', plusFile);

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

        console.log('AI Karaoke Debug: Displaying', this.slides.length, 'slides in big format');

        // Создаем основные большие слайды
        this.slides.forEach((slide, index) => {
            console.log('AI Karaoke Debug: Slide', index, 'data:', {
                text: slide.text,
                image: slide.image,
                start: slide.start,
                end: slide.end
            });

            const slideElement = document.createElement('div');
            slideElement.className = 'karaoke-slide';
            slideElement.id = `slide-${index}`;

            // Устанавливаем фоновое изображение
            if (slide.image) {
                slideElement.style.backgroundImage = `url(${slide.image})`;
            } else {
                slideElement.style.background = 'linear-gradient(135deg, #1a1a2e, #16213e)';
            }

            // Преобразуем переносы строк в HTML <br> теги
            const formattedText = slide.text.replace(/\n/g, '<br>');

            slideElement.innerHTML = `
                <div class="slide-content">
                    <div class="slide-text">${formattedText}</div>
                    <div class="slide-timing">${this.formatTime(slide.start)} - ${this.formatTime(slide.end)}</div>
                </div>
            `;

            // Только обычный клик для перехода к времени, БЕЗ двойного клика
            slideElement.addEventListener('click', () => {
                if (this.audioElement) {
                    this.audioElement.currentTime = slide.start;
                }
            });

            container.appendChild(slideElement);
        });

        // Добавляем навигационные точки
        this.createSlideNavigation(container);

        // Добавляем миниатюры
        this.createSlideThumbnails();

        // Показываем первый слайд
        this.showSlide(0);

        console.log('AI Karaoke Debug: All slides added to container in big format');
    }

    createSlideNavigation(container) {
        const navigation = document.createElement('div');
        navigation.className = 'slides-navigation';
        navigation.id = 'slides-navigation';

        this.slides.forEach((slide, index) => {
            const dot = document.createElement('div');
            dot.className = 'slide-nav-dot';
            dot.addEventListener('click', () => this.showSlide(index));
            navigation.appendChild(dot);
        });

        container.appendChild(navigation);
    }

    createSlideThumbnails() {
        const player = document.getElementById('karaoke-player');
        if (!player) return;

        // Удаляем существующие миниатюры
        const existingThumbnails = player.querySelector('.slides-thumbnails');
        if (existingThumbnails) {
            existingThumbnails.remove();
        }

        const thumbnailsContainer = document.createElement('div');
        thumbnailsContainer.className = 'slides-thumbnails';
        thumbnailsContainer.id = 'slides-thumbnails';

        this.slides.forEach((slide, index) => {
            const thumbnail = document.createElement('div');
            thumbnail.className = 'slide-thumbnail';
            thumbnail.id = `thumbnail-${index}`;

            // Устанавливаем фоновое изображение для миниатюры
            if (slide.image) {
                thumbnail.style.backgroundImage = `url(${slide.image})`;
            } else {
                thumbnail.innerHTML = '<div class="slide-thumbnail-placeholder">🎵</div>';
            }

            // Добавляем текст миниатюры
            const thumbnailText = document.createElement('div');
            thumbnailText.className = 'slide-thumbnail-text';
            thumbnailText.textContent = slide.text.substring(0, 30) + (slide.text.length > 30 ? '...' : '');
            thumbnail.appendChild(thumbnailText);

            // Обработчик клика
            thumbnail.addEventListener('click', () => {
                this.showSlide(index);
                if (this.audioElement) {
                    this.audioElement.currentTime = slide.start;
                }
            });

            thumbnailsContainer.appendChild(thumbnail);
        });

        player.appendChild(thumbnailsContainer);
    }

    showSlide(index) {
        // Скрываем все слайды
        document.querySelectorAll('.karaoke-slide').forEach(slide => {
            slide.classList.remove('active');
        });

        // Убираем активный класс с навигационных точек
        document.querySelectorAll('.slide-nav-dot').forEach(dot => {
            dot.classList.remove('active');
        });

        // Убираем активный класс с миниатюр
        document.querySelectorAll('.slide-thumbnail').forEach(thumbnail => {
            thumbnail.classList.remove('active');
        });

        // Показываем текущий слайд
        const currentSlide = document.getElementById(`slide-${index}`);
        if (currentSlide) {
            currentSlide.classList.add('active');
        }

        // Активируем навигационную точку
        const navDots = document.querySelectorAll('.slide-nav-dot');
        if (navDots[index]) {
            navDots[index].classList.add('active');
        }

        // Активируем миниатюру
        const thumbnail = document.getElementById(`thumbnail-${index}`);
        if (thumbnail) {
            thumbnail.classList.add('active');
            // Прокручиваем к активной миниатюре
            thumbnail.scrollIntoView({ behavior: 'smooth', inline: 'center' });
        }

        this.currentSlide = index;
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
            // Показываем новый слайд
            this.showSlide(newSlide);
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
        this.showSlide(0); // Показываем первый слайд
        document.getElementById('play-karaoke-btn').textContent = 'Воспроизвести';
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

    showNotification(message) {
        // Создаем уведомление
        const notification = document.createElement('div');
        notification.className = 'karaoke-notification';
        notification.textContent = message;

        // Добавляем стили
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 10000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-family: inherit;
            font-size: 14px;
            max-width: 300px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;

        document.body.appendChild(notification);

        // Анимация появления
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Удаляем через 4 секунды
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 4000);
    }
}

// Инициализация при загрузке страницы
const karaokePlayer = new KaraokePlayer();
