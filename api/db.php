<?php
/* ============================================================
   Conexão SQLite + criação automática do schema
   ============================================================ */
require_once __DIR__ . '/config.php';

function db() {
  static $pdo = null;
  if ($pdo !== null) return $pdo;

  $dir = dirname(DB_PATH);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  $pdo = new PDO('sqlite:' . DB_PATH);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  $pdo->exec("CREATE TABLE IF NOT EXISTS attendants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    whatsapp TEXT NOT NULL,
    role TEXT DEFAULT '',
    photo TEXT DEFAULT '',
    active INTEGER NOT NULL DEFAULT 1,
    queue_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )");
  // migração: coluna photo em bancos já existentes
  $acols = [];
  foreach ($pdo->query("PRAGMA table_info(attendants)") as $c) $acols[$c['name']] = true;
  if (empty($acols['photo'])) $pdo->exec("ALTER TABLE attendants ADD COLUMN photo TEXT DEFAULT ''");

  $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT,
    whatsapp TEXT,
    localizacao TEXT,
    motivo_uso TEXT,
    dispositivo TEXT,
    internet_atual TEXT,
    ativacao TEXT,
    attendant_id INTEGER,
    attendant_name TEXT,
    ip TEXT,
    user_agent TEXT,
    device TEXT,
    conn_type TEXT,
    geo_city TEXT,
    geo_region TEXT,
    geo_country TEXT,
    geo_isp TEXT,
    extra TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )");

  // migração: adiciona colunas novas em bancos já existentes
  $have = [];
  foreach ($pdo->query("PRAGMA table_info(leads)") as $c) $have[$c['name']] = true;
  $add = [
    'ip' => 'TEXT', 'user_agent' => 'TEXT', 'device' => 'TEXT', 'conn_type' => 'TEXT',
    'geo_city' => 'TEXT', 'geo_region' => 'TEXT', 'geo_country' => 'TEXT',
    'geo_isp' => 'TEXT', 'extra' => 'TEXT'
  ];
  foreach ($add as $col => $type) {
    if (empty($have[$col])) $pdo->exec("ALTER TABLE leads ADD COLUMN $col $type");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
  )");

  return $pdo;
}

/* ---- helpers de settings (usados para o índice round-robin) ---- */
function setting_get($key, $default = null) {
  $st = db()->prepare("SELECT value FROM settings WHERE key = ?");
  $st->execute([$key]);
  $r = $st->fetch();
  return $r ? $r['value'] : $default;
}
function setting_set($key, $value) {
  $st = db()->prepare("INSERT INTO settings (key,value) VALUES (?,?)
    ON CONFLICT(key) DO UPDATE SET value = excluded.value");
  $st->execute([$key, (string)$value]);
}

/* ============================================================
   Seleção do próximo atendente em FILA (round-robin)
   - considera apenas atendentes ativos
   - ordena por queue_order e depois por data de criação
   - avança o índice guardado em settings
   ============================================================ */
function next_attendant() {
  $rows = db()->query("SELECT * FROM attendants WHERE active = 1
                       ORDER BY queue_order ASC, id ASC")->fetchAll();
  if (count($rows) === 0) return null;

  $last = (int) setting_get('round_robin_index', -1);
  $next = ($last + 1) % count($rows);
  setting_set('round_robin_index', $next);

  return $rows[$next];
}
