# ИИ Караоке Генератор

Система для автоматической генерации караоке-слайдов с использованием искусственного интеллекта OpenAI.

## Возможности

- ✅ Загрузка минусовки и плюсовки
- ✅ Ввод текста песни
- ✅ Автоматическая генерация таймингов с помощью ИИ OpenAI GPT-4
- ✅ Синхронизированные слайды с текстом
- ✅ Интерактивный плеер с прогресс-баром
- ✅ Переход к слайдам по клику
- ✅ Адаптивный дизайн

## Установка и настройка

### 1. Настройка OpenAI API

1. Перейдите на [platform.openai.com](https://platform.openai.com/api-keys)
2. Создайте новый API ключ
3. Откройте файл `settings.php` в браузере
4. Введите ваш API ключ и сохраните

### 2. Требования сервера

- PHP 7.4+
- Битрикс 24
- Поддержка загрузки файлов (upload_max_filesize >= 50M)
- FFprobe (опционально, для точного определения длительности аудио)

### 3. Права доступа

Убедитесь, что директория `/upload/aikaraoke/` имеет права на запись:

```bash
chmod 755 /path/to/bitrix/upload/aikaraoke/
```

## Использование

1. Откройте главную страницу караоке-генератора
2. Нажмите "Загрузить файлы"
3. Выберите:
   - **Минусовка** - аудио файл без вокала
   - **Плюсовка** - аудио файл с вокалом (для анализа)
   - **Текст песни** - каждая строка на новой строке
4. Нажмите "Генерировать караоке"
5. Дождитесь обработки ИИ
6. Воспроизводите готовое караоке!

## Структура файлов

```
aiKaraoke/
├── index.php              # Главная страница
├── generate_karaoke.php   # Обработчик генерации
├── settings.php           # Настройки API ключа
├── .section.php          # Название раздела
├── css/
│   └── karaoke.css       # Стили интерфейса
└── js/
    └── karaoke.js        # JavaScript логика
```

## Как работает ИИ-генерация

1. **Анализ аудио**: Определяется длительность трека
2. **Обработка текста**: Текст разбивается на строки
3. **ИИ-анализ**: GPT-4 анализирует структуру песни и создает оптимальные тайминги
4. **Fallback**: Если ИИ недоступен, используется равномерное распределение
5. **Генерация слайдов**: Создаются синхронизированные слайды

## Поддерживаемые форматы

- **Аудио**: MP3, WAV, OGG, M4A
- **Текст**: Обычный текст, каждая строка = один слайд

## Устранение неполадок

### Ошибка "Ключ API не найден"
- Проверьте настройки в `settings.php`
- Убедитесь, что ключ действителен

### Ошибка загрузки файлов
- Проверьте размер файлов (лимит: 50MB)
- Убедитесь в правах доступа к директории uploads

### ИИ не генерирует тайминги
- Проверьте баланс OpenAI аккаунта
- Система автоматически переключится на равномерное распределение

## Технические детали

- **Frontend**: Vanilla JavaScript, CSS3
- **Backend**: PHP, Битрикс API
- **ИИ**: OpenAI GPT-4
- **Аудио**: Web Audio API, HTML5 Audio

## Лицензия

Разработано для Битрикс24
