# Codehub — тестовое задание (Currency Rates API)

Symfony-приложение для получения курсов валют из внешних API, сохранения их в JSON-файл
и предоставления REST-эндпоинтов для чтения курсов и конвертации валют.

## Структура проекта

```
codehub/
├── docker/                   # Docker-конфигурация окружения
│   ├── .env                  # Переменные docker-compose (пути, сеть, xdebug и т.д.)
│   ├── docker-compose.yml    # Сборка/запуск контейнеров php + nginx
│   ├── php/Dockerfile        # PHP-FPM 8.5 (Alpine, лёгкий образ) + intl, zip, pdo_mysql, mbstring, xdebug; запускается от непривилегированного пользователя
│   ├── php/conf.d/php.ini    # Общие настройки PHP (memory_limit, error_reporting и т.д.) перекрывают дефолтные значения
│   ├── php/conf.d/xdebug.ini # Настройки Xdebug (управляются через переменные окружения)
│   └── nginx/default.conf    # Конфигурация Nginx (front controller Symfony)
├── app/                      # Symfony-приложение (само тестовое задание)
└── README.md
```

Docker-конфигурация и код приложения намеренно разделены на разные папки (`docker/` и `app/`),
чтобы окружение и бизнес-логика не смешивались.

## Требования

- Установить Docker + Docker Compose
- Для локальной разработки без Docker (опционально): [Symfony CLI](https://symfony.com/download), PHP >= 8.4, Composer

## Вариант 1 — запуск через Docker

```bash
cd docker
docker compose build
docker compose up -d
```

Приложение будет доступно на `http://localhost:8088` (порт задаётся переменной
`NGINX_PORT` в файле `docker/.env`, по умолчанию `8088`, чтобы не конфликтовать с другими
локальными проектами на 8080/80).

Все параметры docker-compose вынесены в `docker/.env`:

| Переменная         | По умолчанию              | Назначение                                                   |
|--------------------|---------------------------|-----------------------------------------------------------|
| `APP_PATH`          | `../app`                   | Путь к Symfony-приложению, монтируется в php и nginx      |
| `PHP_CONFIG_PATH`   | `./php/conf.d`             | Папка с кастомными `*.ini` (подключается через `PHP_INI_SCAN_DIR`) |
| `UID` / `GID`       | `1000` / `1000`            | Владелец непривилегированного пользователя `app` в PHP-контейнере (совпадает с uid/gid хоста) |
| `NETWORK_NAME`      | `codehub`                  | Имя docker-сети                                               |
| `NETWORK_SUBNET`    | `172.29.0.0/24`            | Подсеть docker-сети (чтобы не пересекаться с другими проектами)         |
| `NGINX_PORT`        | `8088`                     | Host-порт nginx                                              |
| `XDEBUG_*`          | см. раздел ниже             | Настройки Xdebug                                              |

`PHP_CONFIG_PATH` монтируется целиком как `/usr/local/etc/php/custom.d`, а `PHP_INI_SCAN_DIR`
указывает PHP читать его вместе со стандартным `conf.d` — любой новый `*.ini` файл
в этой папке подхватывается автоматически, без правок в `docker/docker-compose.yml`.

Остановить контейнеры:

```bash
cd docker
docker compose down
```

Выполнение консольных команд Symfony внутри контейнера:

```bash
cd docker
docker compose exec php php bin/console <command>
```

PHP-контейнер запускается от непривилегированного пользователя `app` (`UID`/`GID` в `docker/.env`, по умолчанию `1000:1000`),
поэтому файлы `var/cache`, `var/log` и `var/data` создаются с правами хостового пользователя и не требуют `chown`.

### Отладка через Xdebug (Docker)

Xdebug уже установлен в PHP-образе и включён по умолчанию (`XDEBUG_MODE=debug` в `docker/.env`).
Настройки передаются в контейнер через переменные окружения (`docker/docker-compose.yml` →
`php/conf.d/xdebug.ini`), поэтому их можно менять без пересборки образа — достаточно
отредактировать `docker/.env` и перезапустить контейнер (`cd docker && docker compose up -d php`).

| Переменная            | По умолчанию            | Назначение                          |
|-----------------------|--------------------------|--------------------------------------|
| `XDEBUG_MODE`         | `debug`                  | `off`, чтобы отключить Xdebug        |
| `XDEBUG_CLIENT_HOST`  | `host.docker.internal`   | Куда Xdebug шлёт соединение (IDE)    |
| `XDEBUG_CLIENT_PORT`  | `9003`                   | Порт, который слушает IDE            |
| `XDEBUG_IDE_KEY`      | `PHPSTORM`                | IDE key                              |

Настройка PhpStorm:
1. **Settings → PHP → Debug** — убедиться, что порт для Xdebug — `9003`.
2. **Settings → PHP → Servers** — добавить сервер с именем `codehub` (совпадает с
   `XDEBUG_SERVER_NAME`), host `localhost`, port `8088`, use path mappings:
   `app` (на диске) ↔ `/var/www/html` (в контейнере).
3. Включить **"Start Listening for PHP Debug Connections"** и поставить breakpoint.

`host.docker.internal` на Linux работает благодаря `extra_hosts: host-gateway` в
`docker/docker-compose.yml` (на Mac/Windows Docker Desktop резолвит его сам).

## Вариант 2 — локальный сервер через Symfony CLI (без Docker)

```bash
cd app
symfony server:start -d --no-tls --port=8000
```

Приложение будет доступно на `http://127.0.0.1:8000`.

Полезные команды:

```bash
symfony server:status   # статус локального сервера
symfony server:log      # просмотр логов
symfony server:stop     # остановка
```

> По умолчанию Symfony CLI слушает только `127.0.0.1`. Флаг `--no-tls` отключает
> самоподписанный HTTPS-сертификат (для его использования достаточно один раз
> выполнить `symfony server:ca:install`).

Xdebug для этого варианта отдельно настраивать не нужно — он уже установлен и включён
в PHP хоста (`php -v` показывает `with Xdebug`), IDE-порт по умолчанию `9003` совпадает
с дефолтным портом PhpStorm.

## Currency Rates API

### 1. Получение курсов (провайдеры → JSON)

```bash
cd docker
docker compose exec php php bin/console app:rates:fetch
```

Опрашивает каждый зарегистрированный `RateProviderInterface` (AutoconfigureTag `app.rate_provider`) и
сохраняет результат каждого отдельным JSON-файлом в `app/var/data/`:
- `fiat_rates.json` — фиатные валюты с [FloatRates](https://www.floatrates.com/) (база USD)
- `crypto_rates.json` — крипто-тикеры с [CoinPaprika](https://coinpaprika.com/api/)

Файлы независимы: если один провайдер упал или вернул пустой результат, данные другого не
затрагиваются, а старый файл не перезаписывается (команда лишь предупреждает и завершается
с ненулевым кодом).

Конфигурируется через `app/.env`:

| Переменная            | По умолчанию                                 | Назначение                        |
|-----------------------|-----------------------------------------------|----------------------------------|
| `FLOATRATES_API_URL`  | `https://www.floatrates.com/daily/usd.json`    | Источник фиатных курсов           |
| `COINPAPRIKA_API_URL` | `https://api.coinpaprika.com/v1/tickers`       | Источник крипто-курсов          |
| `COINPAPRIKA_COINS`   | `btc-bitcoin,eth-ethereum`                      | Coin id через запятую (`csv:` env processor) |

### 2. Получение курсов

```
GET /api/rates
GET /api/rates?base=EUR
```

Объединяет fiat + crypto, пересчитывает к запрошенной `base` (по умолчанию USD) и отдаёт **массив** объектов `{rate, code}`. Возвращаемый массив отсортирован по `code`:

```bash
GET /api/rates?base=EUR
```

```json
[
    {"rate": 1.0895752726505115, "code": "USD"},
    {"rate": 1, "code": "EUR"}
]
```

```bash
GET /api/rates
```

```json
[
    {"rate": 1, "code": "USD"},
    {"rate": 0.91778881652425, "code": "EUR"}
]
```

### 3. Конвертация валют

```
GET /api/convert?from=USD&to=EUR&amount=100
```

```json
{
    "amount": 85.95,
    "currency_from": {"rate": 1, "code": "USD"},
    "currency_to": {"rate": 0.91778881652425, "code": "EUR"}
}
```

### Ошибки

Любой `/api/*` эндпоинт отдаёт единый формат ошибки `{"error": "..."}` (см. `App\EventListener\ApiExceptionListener`):

| Код | Когда                                                                       |
|-----|------------------------------------------------------------------------------|
| 400 | Невалидные параметры (`amount` не число/не положительное, пустой `from`/`to`) |
| 404 | Неизвестный код валюты/монеты                                                       |
| 503 | `app:rates:fetch` ещё ни разу не выполнялась успешно                                        |

### Архитектура (`app/src/`)

```
Rates/
├── RateProviderInterface.php          # контракт провайдера, автотегируется через #[AutoconfigureTag]
├── Provider/FloatRatesProvider.php, CoinPaprikaProvider.php
├── RatesFileStorage.php               # чтение/атомарная запись JSON (Symfony Filesystem)
├── RatesRepository.php                # read-side: мерж провайдеров, rebase, convert
└── Exception/                         # UnknownCurrencyException, RatesUnavailableException
Command/FetchRatesCommand.php          # write-side: вызывает провайдеры, пишет через RatesFileStorage
Controller/RatesController.php         # #[MapQueryParameter] / #[MapQueryString] + Validator
EventListener/ApiExceptionListener.php # единый JSON-ответ об ошибках для /api/*
```

Новый провайдер курсов добавляется без правок в контейнере/команде/репозитории — достаточно реализовать
`RateProviderInterface` (Symfony автоматически делает Autowire и AutoconfigureTag через `services.yaml` `App\: resource:`).

### Тесты

```bash
cd docker
docker compose exec php php bin/phpunit
```

25 тестов: unit (`RatesRepository`, оба провайдера через `MockHttpClient`, команда через `CommandTester`) +
functional (`RatesController` через `WebTestCase`, с изолированной директорией данных `var/test_data`
через `when@test` в `services.yaml`).