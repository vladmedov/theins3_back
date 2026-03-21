# The Insider v.3 — Backend

Серверная часть проекта на **Laravel 12** и **Laravel Nova**. Репозиторий отвечает за API, админку и фоновые процессы; публичный фронтенд живет отдельно и работает с этим бэкендом по HTTP/API.

## Что здесь есть

- Laravel-приложение с Nova-админкой
- PostgreSQL как основная база
- Redis для кэша, сессий и очередей
- Elasticsearch для поиска через Laravel Scout
- Docker-окружение для локальной разработки
- Инструменты для импорта данных из legacy-базы

## Требования

### Рекомендуемый вариант: Docker

- Docker Engine
- Docker Compose v2
- `dev/auth.json` с ключом к [nova.laravel.com](https://nova.laravel.com) для установки приватных Composer-зависимостей

Файл с примером: `dev/auth.json.example`.

### Вариант без Docker

- PHP `8.2+`
- Composer `2`
- Node.js + npm
- PostgreSQL
- Redis
- Elasticsearch
- PHP-расширения для Laravel/Nova, используемые в проекте: `pdo_pgsql`, `zip`, `gd`, `exif`, `imagick`, `redis` и другие из `Dockerfile`

## Сервисы приложения

Ниже перечислены внешние сервисы, которые ожидает приложение.

| Сервис | Обязателен | Назначение |
|--------|------------|------------|
| PostgreSQL | Да | Основная база приложения |
| Redis | Да | Кэш, сессии, очереди |
| Elasticsearch | Да | Поиск и индексация через Laravel Scout |
| nginx + PHP-FPM | Да | Веб-слой и обработка HTTP-запросов |
| Laravel scheduler | Да | Периодический запуск `schedule:run` |
| PostgreSQL legacy | Нет | Нужен для команд `legacy:*` и импорта старых данных |
| Kibana | Нет | Удобна для отладки Elasticsearch |
| pgAdmin | Нет | Удобна для ручной работы с PostgreSQL |

В локальном Docker-стеке этим ролям соответствуют сервисы из `dev/docker-compose.dev.xml`: `backend`, `backend-scheduler`, `db`, `redis`, `elasticsearch`, `legacy_db`, `nginx`, `kibana`, `pgadmin`.

## Быстрый старт

### 1. Подготовить окружение

```bash
cp .env.example .env
cp dev/auth.json.example dev/auth.json
```

После этого заполни `.env` и `dev/auth.json` реальными значениями.

### 2. Собрать PHP-образ

```bash
./dev.sh build backend
```

Альтернатива через npm:

```bash
npm run dc -- build backend
```

Если нужен прямой `docker buildx`, образ собирается из `Dockerfile`, а секрет Composer передается так:

```bash
DOCKER_BUILDKIT=1 docker buildx build \
  --secret id=composer_auth,src=dev/auth.json \
  --target production \
  -t theins3-php:dev .
```

### 3. Поднять стек

```bash
./dev.sh up -d
```

Или:

```bash
npm run dc -- up -d
```

### 4. Проверить сервисы

```bash
npm run dc -- ps
```

Основной PHP-сервис называется `backend`. Для artisan-команд используй именно его, а не старое имя `app`.

## Повседневные команды

### Artisan внутри контейнера

```bash
npm run artisan -- migrate
npm run artisan -- optimize:clear
npm run artisan -- process:view-counts
npm run scout:reindex
npm run scout:info
```

`npm run artisan -- ...` выполняет `php artisan` внутри контейнера `backend`.

Для переиндексации `Post` есть отдельный shortcut. Он вызывает штатную команду пакета `scout:import` внутри контейнера `backend`.

```bash
npm run scout:reindex
```

Эквивалент без npm-алиасов:

```bash
npm run dc -- exec backend php artisan migrate
npm run dc -- exec backend php artisan scout:import "App\\Models\\Post"
```

Для просмотра состояния Elasticsearch через Docker есть отдельные команды:

```bash
npm run scout:health
npm run scout:indices
npm run scout:aliases
npm run scout:info
```

`scout:info` выводит cluster health, список индексов и список alias за один запуск.

### Логи и отладка

```bash
./dev.sh logs -f backend
./dev.sh logs -f nginx
./dev.sh logs -f elasticsearch
```

### Остановка и перезапуск

```bash
./dev.sh down
./dev.sh up -d
```

## Планировщик задач

В Docker для периодических задач используется отдельный сервис `backend-scheduler`. Он в бесконечном цикле вызывает `php artisan schedule:run` раз в минуту, а само расписание описано в `routes/console.php`.

Сейчас по расписанию выполняются:

| Команда | Частота | Назначение |
|--------|---------|------------|
| `process:view-counts` | каждые 5 минут | Агрегирует и сохраняет просмотры публикаций |
| `update:currencies` | каждые 4 часа | Обновляет курсы валют |
| `update:oil` | каждые 4 часа | Обновляет данные по цене нефти |
| `generate:sitemap` | каждый час | Пересобирает sitemap |

Для всех задач включен `withoutOverlapping()`, поэтому Laravel не запускает новый экземпляр команды, пока не завершился предыдущий.

Есть также заготовка для `sync:legacy`, но сейчас она закомментирована и по расписанию не выполняется.

## Данные локального окружения

Каталоги с данными контейнеров создаются под `dev/data/` и не коммитятся в git.

Используются под:

- `db`
- `legacy_db`
- `redis`
- `elasticsearch`
- `pgadmin`

Если локально возникают проблемы с Elasticsearch после рестарта, проверь состояние диска: при почти заполненном разделе Elasticsearch может перевести индексы в режим read-only.

## Legacy-импорт

Для импорта из старой системы нужен отдельный PostgreSQL-сервис `legacy_db`.

Большой дамп в формате `pg_dump -Fd` ожидается по пути `dev/theins_prod_09112025_tar` и не хранится в git.

Восстановление дампа:

```bash
dev/restore-legacy-db.sh
```

Запуск основного импорта:

```bash
npm run artisan -- legacy:import_main
```

Если нужен поиск после полного импорта постов, индекс можно заполнить отдельно:

```bash
npm run scout:reindex
```

Обычная автосинхронизация поиска для `Post` продолжает работать сама. На пустом Elasticsearch приложение теперь сначала bootstrap-ит write alias для `posts`, чтобы следующие автосохранения и ручная переиндексация использовали одну и ту же схему индексации.

## Фронтенд

Публичный сайт находится в отдельном репозитории. В полной схеме он обычно поднимается отдельным сервисом, а этот бэкенд обслуживает API, админку и внутренние процессы.

## Продакшен

`dev/docker-compose.dev.xml` служит ориентиром по составу сервисов, но не является готовым production-манифестом.

Для production стоит предусмотреть:

- хранение секретов вне репозитория и вне Docker-образа
- отдельные процессы для `php-fpm`, очередей и планировщика
- внешний веб-слой перед PHP-FPM
- управляемые тома и резервное копирование для PostgreSQL, Redis и Elasticsearch
- мониторинг, healthchecks и алерты по очередям, диску и ошибкам приложений

## Лицензия

Laravel распространяется по лицензии [MIT](https://opensource.org/licenses/MIT). Код проекта и зависимости, включая Nova, распространяются по своим лицензиям.
