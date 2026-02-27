# XML Редактор — Яндекс.Врачи

Веб-приложение для создания и редактирования XML-фидов в формате **Яндекс.Врачи**. Позволяет управлять данными о врачах, клиниках, услугах и предложениях через удобный интерфейс без ручного редактирования XML.

## Возможности

- **Мультипроектность** — несколько изолированных проектов, каждый в отдельном SQLite-файле
- **Импорт XML** — загрузка готового фида с разбором всех секций (врачи, клиники, услуги, офферы)
- **Экспорт XML** — генерация валидного XML строго по схеме Яндекс.Врачи
- **Врачи** — полная карточка: ФИО, фото, стаж, образование, места работы, сертификаты, степень, категория
- **Клиники** — название, адрес, телефон, логотип, интеграция с Яндекс.Бизнес
- **Услуги** — название, код Номенклатуры Минздрава (`gov_id`)
- **Предложения (офферы)** — связь врач × клиника × услуга с ценой и флагами (ОМС, телемед, вызов на дом и др.)
- **Справочник специальностей** — datalist-подсказки при вводе специальности в оффере
- **Авто-генерация ID** — из фамилии врача (транслитерация) и из пары врач+клиника для оффера
- **Авто-выбор клиники** — если в проекте одна клиника, подставляется автоматически
- **Карточка врача с офферами** — список всех предложений врача прямо на странице редактирования
- **Аккордеоны** — секции образования, мест работы, сертификатов и офферов сворачиваются

## Стек

- **PHP 8.1+** — без фреймворков, без Composer
- **SQLite** через PDO — один файл на проект, схема создаётся автоматически
- **Bootstrap 5.3.3** — CDN, без npm
- **Alpine.js 3.14.1** — CDN, реактивные формы (динамические блоки, авто-ID)
- **SimpleXML** — парсинг при импорте
- **DOMDocument** — генерация XML при экспорте

## Установка

### Требования
- PHP 8.1+ с расширениями: `pdo_sqlite`, `simplexml`, `dom`
- Веб-сервер (Apache или Nginx) с поддержкой PHP
- Директория `data/` доступна для записи PHP-процессу

### Шаги

```bash
# 1. Клонировать репозиторий в корень домена
git clone https://github.com/yourname/xmlredactor.git /var/www/html

# 2. Создать конфиг из шаблона
cp config.example.php config.php

# 3. Выдать права на запись в папку data/
chmod 755 data

# 4. Открыть в браузере и задать пароль
# https://yourdomain.ru/setup.php
```

> `data/.htaccess` уже включён в репозиторий и защищает SQLite-файлы от прямого скачивания через Apache.
> Для Nginx добавьте в конфиг сервера:

```nginx
location ~* /data/ {
    deny all;
}
```

### Первый запуск

Откройте `/setup.php` → задайте пароль → войдите → создайте первый проект.

## Структура проекта

```
xmlredactor/
├── config.php          # Пароль (bcrypt hash) + DATA_DIR
├── auth.php            # Авторизация (сессии)
├── db.php              # PDO helper, схема SQLite, вспомогательные функции
├── layout.php          # Общий layout (Bootstrap navbar + sidebar)
├── setup.php           # Первоначальная настройка / сброс пароля
├── import.php          # Импорт XML → SQLite
├── export.php          # SQLite → XML (скачивание)
├── doctor/             # CRUD врачей
├── clinic/             # CRUD клиник
├── service/            # CRUD услуг
├── offer/              # CRUD предложений
├── specialty/          # Справочник специальностей
├── docs/
│   ├── TECHNICAL.md    # Техническая документация
│   └── USER_GUIDE.md   # Руководство пользователя
└── data/               # SQLite-файлы (не в репозитории)
```

## Документация

- [Руководство пользователя](docs/USER_GUIDE.md) — как работать с интерфейсом
- [Техническая документация](docs/TECHNICAL.md) — архитектура, схема БД, особенности реализации

## Формат XML

Приложение генерирует XML по официальной схеме Яндекс.Врачи:

```xml
<?xml version="1.0" encoding="utf-8"?>
<shop version="2.0" date="2024-12-10 08:06">
  <name>Яндекс.Здоровье</name>
  <company>ООО Яндекс.Врачи</company>
  <url>https://doctors.example.ru/</url>
  <email>info@example.ru</email>

  <doctors>
    <doctor id="doctor_1">
      <name>Виноградов Александр Эдмондович</name>
      <first_name>Александр</first_name>
      <surname>Виноградов</surname>
      <patronymic>Эдмондович</patronymic>
      <experience_years>9</experience_years>
      <career_start_date>2015-01-01</career_start_date>
      <degree>доктор наук</degree>
      <rank>Профессор</rank>
      <category>Первая</category>
      <education>
        <organization>Медицинский университет Реавиз</organization>
        <finish_year>2015</finish_year>
        <type>Специалитет</type>
        <specialization>Лечебное дело</specialization>
      </education>
      <job>
        <organization>Центр детской неврологии "НейроСпектр"</organization>
        <period_years>2015-2017</period_years>
        <position>Нейропсихолог</position>
      </job>
      <certificate>
        <organization>Московский институт психоанализа</organization>
        <finish_year>2020</finish_year>
        <name>Лечебная физкультура и спортивная медицина</name>
      </certificate>
    </doctor>
  </doctors>

  <clinics>
    <clinic id="clinic_1">
      <name>Клиника Яндекс Здоровье</name>
      <city>г. Москва</city>
      <address>ул. Льва Толстого 16</address>
      <url>https://www.someclinic.ru</url>
      <email>info@someclinic.ru</email>
      <phone>+79999999999</phone>
      <internal_id>123</internal_id>
      <company_id>1032739194</company_id>
    </clinic>
  </clinics>

  <services>
    <service id="service_1">
      <name>Первичный приём</name>
      <gov_id>A01.07.001</gov_id>
      <description>Первичный приём стоматолога-хирурга</description>
      <internal_id>123</internal_id>
    </service>
  </services>

  <offers>
    <offer id="offer_1">
      <url>https://doctors.example.ru/dr/vinogradov-aleksandr-edmondovich/</url>
      <appointment>true</appointment>
      <online_schedule>true</online_schedule>
      <oms>true</oms>
      <price>
        <base_price>5200</base_price>
        <currency>RUB</currency>
        <free_appointment>При условии дальнейшего лечения</free_appointment>
      </price>
      <service id="service_1"/>
      <clinic id="clinic_1">
        <doctor id="doctor_1">
          <speciality>стоматолог-хирург</speciality>
          <adult_appointment>true</adult_appointment>
          <children_appointment>true</children_appointment>
          <house_call>true</house_call>
          <telemed>true</telemed>
          <is_base_service>true</is_base_service>
        </doctor>
      </clinic>
    </offer>
  </offers>
</shop>
```

## Сброс пароля

Создайте файл `data/.reset` через FTP/SSH → откройте `/setup.php` → введите новый пароль. Файл удалится автоматически.

## Лицензия

MIT
