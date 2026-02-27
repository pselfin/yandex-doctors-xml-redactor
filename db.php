<?php
require_once __DIR__ . '/config.php';

function getDb(string $project): PDO {
    $file = DATA_DIR . '/' . preg_replace('/[^a-z0-9_-]/i', '', $project) . '.sqlite';
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    createSchema($pdo);
    return $pdo;
}

function createSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS meta (
            key TEXT PRIMARY KEY,
            value TEXT
        );

        CREATE TABLE IF NOT EXISTS doctors (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            first_name TEXT,
            surname TEXT,
            patronymic TEXT,
            url TEXT,
            description TEXT,
            experience_years INTEGER,
            career_start_date TEXT,
            picture TEXT,
            degree TEXT,
            rank TEXT,
            category TEXT,
            reviews_total_count INTEGER,
            sort_order INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS doctor_education (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id TEXT,
            organization TEXT,
            finish_year INTEGER,
            type TEXT,
            specialization TEXT
        );

        CREATE TABLE IF NOT EXISTS doctor_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id TEXT,
            organization TEXT,
            period_years TEXT,
            position TEXT
        );

        CREATE TABLE IF NOT EXISTS doctor_certificates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id TEXT,
            organization TEXT,
            finish_year INTEGER,
            name TEXT
        );

        CREATE TABLE IF NOT EXISTS clinics (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            city TEXT,
            address TEXT,
            url TEXT,
            picture TEXT,
            email TEXT,
            phone TEXT,
            internal_id TEXT,
            company_id TEXT
        );

        CREATE TABLE IF NOT EXISTS services (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            gov_id TEXT,
            description TEXT,
            internal_id TEXT
        );

        CREATE TABLE IF NOT EXISTS offers (
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

        CREATE TABLE IF NOT EXISTS specialties (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            sort_order INTEGER DEFAULT 0
        );
    ");
    seedSpecialties($pdo);
}

function seedSpecialties(PDO $pdo): void {
    $count = $pdo->query("SELECT COUNT(*) FROM specialties")->fetchColumn();
    if ($count > 0) return;

    $defaults = [
        // Стоматологические
        'стоматолог',
        'стоматолог-терапевт',
        'стоматолог-ортопед',
        'стоматолог-хирург',
        'стоматолог-ортодонт',
        'стоматолог-пародонтолог',
        'стоматолог-имплантолог',
        'стоматолог-эндодонтист',
        'детский стоматолог',
        'гнатолог',
        // Общие
        'анестезиолог-реаниматолог',
        'терапевт',
        'педиатр',
        'хирург',
        'невролог',
        'кардиолог',
        'офтальмолог',
        'оториноларинголог',
        'гинеколог',
        'уролог',
        'эндокринолог',
        'дерматолог',
        'ортопед',
        'травматолог',
        'психиатр',
        'психотерапевт',
        'рентгенолог',
        'физиотерапевт',
    ];

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO specialties (name, sort_order) VALUES (?, ?)");
    foreach ($defaults as $i => $name) {
        $stmt->execute([$name, $i]);
    }
}

function getProjects(): array {
    $files = glob(DATA_DIR . '/*.sqlite');
    $projects = [];
    foreach ($files as $file) {
        $projects[] = basename($file, '.sqlite');
    }
    sort($projects);
    return $projects;
}

function projectExists(string $project): bool {
    $name = preg_replace('/[^a-z0-9_-]/i', '', $project);
    return file_exists(DATA_DIR . '/' . $name . '.sqlite');
}

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function boolStr(mixed $v): string {
    return ($v ? 'true' : 'false');
}
