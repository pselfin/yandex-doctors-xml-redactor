# Техническая документация — XML Редактор (Яндекс.Врачи)

## Назначение проекта

Веб-приложение для создания и редактирования XML-фидов в формате **Яндекс.Врачи** (схема `<shop version="2.0">`). Поддерживает несколько изолированных проектов — по одному на каждую клинику/организацию.

---

## Стек технологий

| Компонент | Версия | Способ подключения |
|-----------|--------|-------------------|
| PHP | 8.1+ | Серверный язык (без фреймворков, без Composer) |
| SQLite | встроен в PHP | PDO + расширение `pdo_sqlite` |
| Bootstrap | 5.3.3 | CDN `cdn.jsdelivr.net` |
| Alpine.js | 3.14.1 | CDN `cdn.jsdelivr.net` (defer) |
| SimpleXML | встроен в PHP | Импорт XML |
| DOMDocument | встроен в PHP | Экспорт XML |

---

## Структура файлов

```
xmlredactor/
├── config.php              # Константы: DATA_DIR, PASSWORD_HASH
├── auth.php                # requireAuth(), login(), logout()
├── db.php                  # getDb(), createSchema(), seedSpecialties(), h(), boolStr()
├── layout.php              # layoutHead(), layoutContent(), layoutFoot()
├── setup.php               # Мастер первоначальной настройки / сброс пароля
├── login.php               # Форма входа
├── logout.php              # Уничтожение сессии
├── change-password.php     # Смена пароля (требует авторизации)
├── index.php               # Список проектов + создать новый
├── project.php             # Дашборд проекта (счётчики, мета <shop>)
├── import.php              # Загрузка XML → SimpleXML → INSERT в SQLite
├── export.php              # SQLite → DOMDocument → XML-файл для скачивания
├── doctor/
│   ├── list.php            # Список врачей с поиском
│   ├── edit.php            # Создание/редактирование врача (Alpine.js)
│   └── delete.php          # Удаление врача + связанных записей
├── clinic/
│   ├── list.php
│   ├── edit.php
│   └── delete.php
├── service/
│   ├── list.php
│   ├── edit.php
│   └── delete.php
├── offer/
│   ├── list.php
│   ├── edit.php            # Alpine.js авто-ID из врача+клиники
│   └── delete.php
├── specialty/
│   ├── list.php            # Список со счётчиком использования
│   ├── edit.php
│   └── delete.php          # Запрещено если специальность используется в оффере
└── data/                   # SQLite-файлы проектов (не в репозитории!)
    └── myclinic.sqlite
```

---

## Схема базы данных

Каждый проект — отдельный SQLite-файл в `data/`. Схема создаётся функцией `createSchema()` при каждом обращении к БД (через `CREATE TABLE IF NOT EXISTS` — идемпотентно).

### Таблица `meta`
Метаданные уровня `<shop>`:
```sql
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT);
-- Ключи: name, company, url, email, picture, version, date
```

### Таблица `doctors`
```sql
CREATE TABLE doctors (
    id TEXT PRIMARY KEY,           -- Латинский slug: timchenko
    name TEXT NOT NULL,            -- Полное ФИО
    first_name TEXT,
    surname TEXT,
    patronymic TEXT,
    url TEXT,
    description TEXT,
    experience_years INTEGER,
    career_start_date TEXT,        -- Формат: YYYY-MM-DD
    picture TEXT,                  -- URL фото
    degree TEXT,                   -- кандидат наук / доктор наук
    rank TEXT,                     -- доцент / профессор
    category TEXT,                 -- первая / вторая / высшая
    reviews_total_count INTEGER,
    sort_order INTEGER DEFAULT 0
);
```

### Таблицы массивов врача (один-ко-многим)
```sql
CREATE TABLE doctor_education (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doctor_id TEXT,
    organization TEXT,
    finish_year INTEGER,
    type TEXT,                     -- Специалитет, Ординатура, …
    specialization TEXT
);

CREATE TABLE doctor_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doctor_id TEXT,
    organization TEXT,
    period_years TEXT,             -- Формат: 2000-2010 или 2015-н.в.
    position TEXT
);

CREATE TABLE doctor_certificates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doctor_id TEXT,
    organization TEXT,
    finish_year INTEGER,
    name TEXT
);
```

### Таблица `clinics`
```sql
CREATE TABLE clinics (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    city TEXT,
    address TEXT,
    url TEXT,
    picture TEXT,
    email TEXT,
    phone TEXT,
    internal_id TEXT,              -- ID в системе онлайн-записи
    company_id TEXT                -- ID в Яндекс.Бизнес
);
```

### Таблица `services`
```sql
CREATE TABLE services (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    gov_id TEXT,                   -- Код Номенклатуры Минздрава: A01.07.001
    description TEXT,
    internal_id TEXT
);
```

### Таблица `offers`
Предложение = связь врач + клиника + услуга:
```sql
CREATE TABLE offers (
    id TEXT PRIMARY KEY,
    service_id TEXT,
    clinic_id TEXT,
    doctor_id TEXT,
    url TEXT,
    online_schedule INTEGER DEFAULT 0,
    appointment INTEGER DEFAULT 1,
    oms INTEGER DEFAULT 0,
    base_price REAL,
    currency TEXT DEFAULT 'RUR',
    discount REAL,
    free_appointment TEXT,
    speciality TEXT,
    children_appointment INTEGER DEFAULT 0,
    adult_appointment INTEGER DEFAULT 1,
    house_call INTEGER DEFAULT 0,
    telemed INTEGER DEFAULT 0,
    is_base_service INTEGER DEFAULT 1
);
```

### Таблица `specialties`
```sql
CREATE TABLE specialties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    sort_order INTEGER DEFAULT 0
);
```
Заполняется 28 значениями по умолчанию при первом создании БД (`seedSpecialties()`). Используется как `<datalist>` в форме оффера.

---

## Авторизация

- **Один пароль** для всего приложения, хранится как bcrypt-хеш в `config.php`
- `PASSWORD_HASH` пустой → редирект на `/setup.php` (первоначальная настройка)
- `PASSWORD_HASH` заполнен → редирект на `/login.php`
- PHP сессии: `$_SESSION['auth'] = true`

### Критическая особенность: запись bcrypt-хеша в файл

**Проблема:** `preg_replace()` интерпретирует `$2y$10$...` в строке замены как backreferences (`$2`, `$1` и т.д.), что необратимо портит хеш.

**Решение:** использовать `str_replace()`:
```php
$content = str_replace(
    "define('PASSWORD_HASH', '" . PASSWORD_HASH . "')",
    "define('PASSWORD_HASH', '" . $hash . "')",
    $content
);
file_put_contents(__DIR__ . '/config.php', $content);
```
Это применено в `setup.php` и `change-password.php`.

### Сброс пароля без доступа к интерфейсу
Создать файл `data/.reset` (через FTP/SSH/cPanel) → зайти на `/setup.php` → ввести новый пароль → файл автоматически удаляется.

---

## Alpine.js: JSON в `<script>` вместо атрибута

**Проблема:** `json_encode()` возвращает JSON с двойными кавычками. При встраивании в HTML-атрибут `x-data="..."` (тоже двойные кавычки) HTML-парсер браузера обрезает значение на первой `"` внутри JSON.

**Решение:** вынести инициализацию в `<script>` блок:
```php
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
// JSON_HEX_TAG экранирует </script> внутри данных → безопасно
```
```html
<script>
function doctorFormData() {
    return {
        education: <?= json_encode($data, $flags) ?>,
        ...
    };
}
</script>
<form x-data="doctorFormData()">
```

---

## Авто-генерация ID

### ID врача (транслитерация)
В `doctor/edit.php`, Alpine.js функция `doctorFormData()`:
- Смотрит на поле `surname` (приоритет) или первое слово `name`
- Транслитерирует через таблицу (щ→shch, ю→yu, я→ya и т.д.)
- Удаляет символы вне `[a-z0-9_-]`
- Флаг `idManual: false` → авто-обновление; при ручном вводе `idManual = true`

### ID оффера (врач + клиника)
В `offer/edit.php`, Alpine.js функция `offerFormData()`:
- Формат: `{doctor_id}_{clinic_id}` (пример: `vinogradov_clinic_1`)
- Обновляется при изменении select'ов врача или клиники
- Тот же принцип с `idManual`

### Авто-выбор клиники
Если в проекте только одна клиника — PHP передаёт её ID в Alpine.js как `$defaultClinicId`:
```php
$defaultClinicId = (count($clinics) === 1) ? $clinics[0]['id'] : '';
```

---

## Импорт XML

`import.php` использует SimpleXML. Алгоритм:
1. Принять файл через `$_FILES['xml']`
2. `simplexml_load_file()` → обход секций `doctors`, `clinics`, `services`, `offers`
3. `$pdo->beginTransaction()` + `INSERT OR REPLACE` для плоских сущностей
4. Для врачей: `DELETE FROM doctor_education WHERE doctor_id=?` → пакетный INSERT (полная перезапись массивов)
5. Вспомогательная функция `xmlBool()`: конвертирует строки `"true"/"false"/"1"/"0"` в int

---

## Экспорт XML

`export.php` генерирует XML через DOMDocument. Строгий порядок секций:
```
<shop version="2.0" date="...">
  <name>...</name>
  <doctors>
    <doctor id="...">
      <education>...</education>
      <job>...</job>
      <certificate>...</certificate>
    </doctor>
  </doctors>
  <clinics><clinic id="...">...</clinic></clinics>
  <services><service id="...">...</service></services>
  <offers>
    <offer id="...">
      <price>
        <base_price>...</base_price>
        <free_appointment>...</free_appointment>  <!-- внутри <price>! -->
      </price>
      <service id="..."/>
      <clinic id="...">
        <doctor id="...">
          <speciality>...</speciality>
          <house_call>true</house_call>    <!-- внутри <doctor>! -->
          <telemed>true</telemed>           <!-- внутри <doctor>! -->
        </doctor>
      </clinic>
    </offer>
  </offers>
</shop>
```

**Важно:** `house_call` и `telemed` — внутри `<doctor>` (внутри `<clinic>` внутри `<offer>`), а не на уровне `<offer>`. `free_appointment` — внутри `<price>`. Эти позиции критичны для прохождения валидатора Яндекса.

Булевые поля: `boolStr($v)` → `"true"` / `"false"` (строки, не 0/1).

---

## Настройки PHP (рекомендуемые)

```ini
; php.ini или .htaccess
upload_max_filesize = 10M
post_max_size = 10M
session.cookie_httponly = 1
session.cookie_samesite = Lax
```

---

## Деплой

1. Скопировать все файлы в корень домена
2. Создать директорию `data/` с правами на запись для PHP-процесса:
   ```bash
   mkdir data && chmod 755 data
   ```
3. Убедиться что `config.php` недоступен напрямую из браузера (закрыть через `.htaccess` или Nginx rules)
4. Открыть `/setup.php` → задать пароль
5. Проверить что `data/*.sqlite` не скачивается браузером — добавить правило:
   ```nginx
   location ~* /data/ { deny all; }
   ```
   или для Apache в `data/.htaccess`:
   ```apache
   Deny from all
   ```

---

## Известные особенности и ловушки

| Проблема | Причина | Решение |
|----------|---------|---------|
| Хеш пароля портится после сохранения | `preg_replace` с `$N` backreferences | Использован `str_replace` |
| Alpine.js не видит данных в `x-data` | JSON с `"` в HTML-атрибуте | Данные в `<script>` блоке |
| `Cannot redeclare boolVal()` | Дубликат функции в двух файлах | Использована `boolStr()` из `db.php` |
| Яндекс: "используйте `offers` вместо `offer`" | `<offer>` добавлялись без обёртки | Добавлен `<offers>` wrapper |
| Пустые поля образования при импорте | Та же проблема Alpine + JSON в атрибуте | Перенос в `<script>` |

---

## Миграция схемы БД

Схема обновляется через `createSchema()` при каждом `getDb()`. Новые таблицы добавляются автоматически. При добавлении новых **колонок** в существующие таблицы нужно явно добавить `ALTER TABLE ... ADD COLUMN` — SQLite не поддерживает автоматический ALTER через `IF NOT EXISTS`.

Пример добавления колонки:
```php
try {
    $pdo->exec("ALTER TABLE doctors ADD COLUMN new_field TEXT");
} catch (PDOException $e) {
    // Колонка уже существует — игнорируем
}
```
