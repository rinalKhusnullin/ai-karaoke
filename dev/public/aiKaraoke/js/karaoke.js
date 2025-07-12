class KaraokePlayer {
    constructor() {
        this.audioContext = null;
        this.currentSlide = 0;
        this.slides = [];
        this.isPlaying = false;
        this.audioElement = null;
        this.timeline = [];
        this.filesUploaded = false;

        // Добавляем переменные для записи
        this.mediaRecorder = null;
        this.recordedChunks = [];
        this.isRecording = false;
        this.micStream = null;
        this.microphoneEnabled = false;

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

            // Добавляем кнопки для управления записью
            const micToggleBtn = document.getElementById('mic-toggle-btn');
            const downloadRecordingBtn = document.getElementById('download-recording-btn');

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

            if (micToggleBtn) {
                micToggleBtn.addEventListener('click', () => this.toggleMicrophone());
            }

            if (downloadRecordingBtn) {
                downloadRecordingBtn.addEventListener('click', () => this.downloadRecording());
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
                this.currentTrackId = result.track_id; // Сохраняем track_id для анализа
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

    async toggleMicrophone() {
        if (!this.microphoneEnabled) {
            try {
                await this.enableMicrophone();
            } catch (error) {
                console.error('Ошибка доступа к микрофону:', error);
                alert('Не удалось получить доступ к микрофону. Проверьте разрешения браузера.');
            }
        } else {
            this.disableMicrophone();
        }
    }

    async enableMicrophone() {
        try {
            // Запрашиваем доступ к микрофону
            this.micStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });

            // Создаем MediaRecorder
            this.mediaRecorder = new MediaRecorder(this.micStream, {
                mimeType: this.getSupportedMimeType()
            });

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.recordedChunks.push(event.data);
                }
            };

            this.mediaRecorder.onstop = () => {
                console.log('Запись остановлена');
                this.processRecording();
            };

            this.microphoneEnabled = true;
            this.updateMicrophoneButton();
            this.showNotification('🎤 Микрофон включен! Теперь при воспроизведении караоке начнется запись.');

        } catch (error) {
            console.error('Ошибка при включении микрофона:', error);
            throw error;
        }
    }

    disableMicrophone() {
        if (this.micStream) {
            this.micStream.getTracks().forEach(track => track.stop());
            this.micStream = null;
        }

        if (this.isRecording) {
            this.stopRecording();
        }

        this.microphoneEnabled = false;
        this.updateMicrophoneButton();
        this.showNotification('🔇 Микрофон выключен');
    }

    getSupportedMimeType() {
        const types = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
            'audio/wav'
        ];

        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }

        return 'audio/webm'; // fallback
    }

    startRecording() {
        if (!this.mediaRecorder || !this.microphoneEnabled) {
            console.warn('Микрофон не включен');
            return;
        }

        if (this.isRecording) {
            console.warn('Запись уже идет');
            return;
        }

        try {
            this.recordedChunks = [];
            this.mediaRecorder.start(100); // записываем данные каждые 100мс
            this.isRecording = true;
            this.updateRecordingStatus();
            console.log('Запись началась');

        } catch (error) {
            console.error('Ошибка при начале записи:', error);
        }
    }

    stopRecording() {
        if (!this.isRecording || !this.mediaRecorder) {
            return;
        }

        try {
            this.mediaRecorder.stop();
            this.isRecording = false;
            this.updateRecordingStatus();
            console.log('Запись остановлена');

        } catch (error) {
            console.error('Ошибка при остановке записи:', error);
        }
    }

    processRecording() {
        if (this.recordedChunks.length === 0) {
            console.warn('Нет данных для обработки');
            return;
        }

        const blob = new Blob(this.recordedChunks, { type: this.getSupportedMimeType() });
        const url = URL.createObjectURL(blob);

        // Показываем кнопку скачивания
        const downloadBtn = document.getElementById('download-recording-btn');
        if (downloadBtn) {
            downloadBtn.style.display = 'inline-block';
            downloadBtn.disabled = false;
        }

        // Сохраняем ссылку для скачивания
        this.recordingUrl = url;
        this.recordingBlob = blob;

        // Добавляем кнопку анализа записи
        this.showAnalysisButton();

        this.showNotification('✅ Запись завершена! Теперь вы можете скачать свою запись или отправить на анализ.');
    }

    showAnalysisButton() {
        // Проверяем, есть ли уже кнопка анализа
        let analysisBtn = document.getElementById('analyze-recording-btn');
        if (!analysisBtn) {
            analysisBtn = document.createElement('button');
            analysisBtn.id = 'analyze-recording-btn';
            analysisBtn.className = 'ui-btn ui-btn-warning sign-ai-karaoke__button';
            analysisBtn.textContent = '🎯 Анализ исполнения';
            analysisBtn.style.display = 'none';

            // Добавляем кнопку после кнопки скачивания
            const downloadBtn = document.getElementById('download-recording-btn');
            if (downloadBtn && downloadBtn.parentNode) {
                downloadBtn.parentNode.insertBefore(analysisBtn, downloadBtn.nextSibling);
            }

            analysisBtn.addEventListener('click', () => this.analyzeRecording());
        }

        analysisBtn.style.display = 'inline-block';
        analysisBtn.disabled = false;
    }

    async analyzeRecording() {
        if (!this.recordingBlob || !this.currentTrackId) {
            alert('Нет записи для анализа или отсутствует ID трека');
            return;
        }

        const analysisBtn = document.getElementById('analyze-recording-btn');
        if (analysisBtn) {
            analysisBtn.disabled = true;
            analysisBtn.textContent = '⏳ Анализирую...';
        }

        try {
            // Создаем FormData для отправки
            const formData = new FormData();

            // Конвертируем WebM в MP3 если нужно, или отправляем как есть
            const audioFile = new File([this.recordingBlob], 'vocal_performance.webm', {
                type: this.recordingBlob.type
            });

            formData.append('vocal_track', audioFile);
            formData.append('track_id', this.currentTrackId);

            const response = await fetch('http://212.113.116.182:8080/api/compare_vocals', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.showAnalysisResults(result);
            } else {
                throw new Error('Анализ не удался');
            }

        } catch (error) {
            console.error('Ошибка анализа записи:', error);
            this.showNotification('❌ Ошибка при анализе записи: ' + error.message, 'error');
        } finally {
            if (analysisBtn) {
                analysisBtn.disabled = false;
                analysisBtn.textContent = '🎯 Анализ исполнения';
            }
        }
    }

    showAnalysisResults(analysisData) {
        // Создаем модальное окно с результатами
        const modal = this.createAnalysisModal(analysisData);
        document.body.appendChild(modal);

        // Показываем модальное окно
        setTimeout(() => {
            modal.style.display = 'block';
            modal.style.opacity = '1';
        }, 100);
    }

    createAnalysisModal(data) {
        const modal = document.createElement('div');
        modal.className = 'analysis-modal';
        modal.id = 'analysis-modal';

        const comparison = data.comparison_analysis;
        const processingInfo = data.processing_info;

        // Определяем цвет оценки на основе общего балла
        const overallScore = comparison.overall_score || 0;
        let scoreColor = '#dc3545'; // красный
        if (overallScore >= 80) scoreColor = '#28a745'; // зеленый
        else if (overallScore >= 60) scoreColor = '#ffc107'; // желтый
        else if (overallScore >= 40) scoreColor = '#fd7e14'; // оранжевый

        modal.innerHTML = `
            <div class="analysis-modal__content">
                <div class="analysis-modal__header">
                    <h2 class="analysis-modal__title">🎤 Результаты анализа вокала</h2>
                    <button class="analysis-modal__close" onclick="this.closest('.analysis-modal').remove()">&times;</button>
                </div>
                
                <div class="analysis-modal__body">
                    <!-- Общая оценка -->
                    <div class="analysis-section">
                        <div class="overall-score" style="background: linear-gradient(135deg, ${scoreColor}20, ${scoreColor}10);">
                            <div class="score-circle" style="border-color: ${scoreColor};">
                                <span class="score-number" style="color: ${scoreColor};">${overallScore}</span>
                                <span class="score-label">из 100</span>
                            </div>
                            <div class="score-description">
                                <h3>Общая оценка исполнения</h3>
                                <p>${this.getScoreDescription(overallScore)}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Детальный анализ -->
                    <div class="analysis-section">
                        <h3>📊 Детальный анализ</h3>
                        <div class="analysis-metrics">
                            ${this.renderMetric('🎵 Точность высоты тона', comparison.pitch_accuracy || 'N/A')}
                            ${this.renderMetric('⏱️ Синхронизация по времени', comparison.timing_accuracy || 'N/A')}
                            ${this.renderMetric('🔊 Качество звука', comparison.audio_quality || 'N/A')}
                            ${this.renderMetric('🎭 Эмоциональная передача', comparison.emotion_match || 'N/A')}
                        </div>
                    </div>
                    
                    <!-- Транскрипция -->
                    ${data.new_vocal_transcript ? `
                    <div class="analysis-section">
                        <h3>📝 Распознанный текст</h3>
                        <div class="transcript-box">
                            ${data.new_vocal_transcript}
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Рекомендации -->
                    ${comparison.recommendations ? `
                    <div class="analysis-section">
                        <h3>💡 Рекомендации</h3>
                        <ul class="recommendations-list">
                            ${comparison.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                    ` : ''}
                    
                    <!-- Кнопки действий -->
                    <div class="analysis-actions">
                        <button class="ui-btn ui-btn-success" onclick="window.karaokePlayer.downloadMixedTrack('${data.mixed_track_url}')">
                            🎵 Скачать финальную запись
                        </button>
                        <button class="ui-btn ui-btn-info" onclick="window.karaokePlayer.downloadVocalTrack('${data.new_vocal_url}')">
                            🎤 Скачать только вокал
                        </button>
                        <button class="ui-btn ui-btn-secondary" onclick="this.closest('.analysis-modal').remove()">
                            Закрыть
                        </button>
                    </div>
                    
                    <!-- Информация о обработке -->
                    <div class="processing-info">
                        <small>
                            Файл: ${processingInfo.original_filename} 
                            (${processingInfo.new_vocal_file_size_mb} МБ)
                        </small>
                    </div>
                </div>
            </div>
        `;

        return modal;
    }

    renderMetric(label, value) {
        let numericValue = parseFloat(value);
        let displayValue = value;
        let barWidth = 0;
        let barColor = '#6c757d';

        if (!isNaN(numericValue)) {
            barWidth = numericValue;
            displayValue = `${numericValue}%`;

            if (numericValue >= 80) barColor = '#28a745';
            else if (numericValue >= 60) barColor = '#ffc107';
            else if (numericValue >= 40) barColor = '#fd7e14';
            else barColor = '#dc3545';
        }

        return `
            <div class="metric-item">
                <div class="metric-label">${label}</div>
                <div class="metric-value">${displayValue}</div>
                <div class="metric-bar">
                    <div class="metric-fill" style="width: ${barWidth}%; background-color: ${barColor};"></div>
                </div>
            </div>
        `;
    }

    getScoreDescription(score) {
        if (score >= 90) return 'Превосходное исполнение! 🌟';
        if (score >= 80) return 'Отличное исполнение! 🎉';
        if (score >= 70) return 'Хорошее исполнение! 👏';
        if (score >= 60) return 'Неплохое исполнение! 👍';
        if (score >= 40) return 'Есть над чем поработать 💪';
        return 'Продолжайте тренироваться! 🎯';
    }

    async downloadMixedTrack(url) {
        try {
            this.showNotification('📥 Скачивание финальной записи...');

            const response = await fetch(url);
            const blob = await response.blob();

            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `karaoke-final-${new Date().toISOString().slice(0,19)}.mp3`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            this.showNotification('✅ Финальная запись скачана!');
        } catch (error) {
            console.error('Ошибка скачивания:', error);
            this.showNotification('❌ Ошибка скачивания файла', 'error');
        }
    }

    async downloadVocalTrack(url) {
        try {
            this.showNotification('📥 Скачивание вокальной дорожки...');

            const response = await fetch(url);
            const blob = await response.blob();

            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `karaoke-vocal-${new Date().toISOString().slice(0,19)}.mp3`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            this.showNotification('✅ Вокальная дорожка скачана!');
        } catch (error) {
            console.error('Ошибка скачивания:', error);
            this.showNotification('❌ Ошибка скачивания файла', 'error');
        }
    }
}

// Инициализация при загрузке страницы
const karaokePlayer = new KaraokePlayer();
