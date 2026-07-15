<?php
/* ============================================================
   Recebe as respostas do quiz, salva o lead, escolhe o próximo
   atendente na fila e devolve o link de WhatsApp já montado.
   ============================================================ */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) { echo json_encode(['ok' => false, 'error' => 'bad_request']); exit; }

function clean($v) { return trim((string)($v ?? '')); }

$lead = [
  'nome'           => clean($in['nome'] ?? ''),
  'whatsapp'       => clean($in['whatsapp'] ?? ''),
  'localizacao'    => clean($in['localizacao'] ?? ''),
  'motivo_uso'     => clean($in['motivo_uso'] ?? ''),
  'dispositivo'    => clean($in['dispositivo'] ?? ''),
  'internet_atual' => clean($in['internet_atual'] ?? ''),
  'ativacao'       => clean($in['ativacao'] ?? 'Imediata'),
];

if ($lead['nome'] === '' || $lead['whatsapp'] === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_fields']); exit;
}

/* ---------- captura de metadados do lead ---------- */
// IP real (considerando proxies / Cloudflare)
function client_ip() {
  foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) {
      $ip = trim(explode(',', $_SERVER[$k])[0]);
      if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
  }
  return $_SERVER['REMOTE_ADDR'] ?? '';
}
$ip = client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// rótulo amigável do dispositivo a partir do User Agent
function device_label($ua) {
  if ($ua === '') return '';
  $os = 'Desconhecido';
  if (preg_match('/iPhone/i', $ua)) $os = 'iPhone (iOS)';
  elseif (preg_match('/iPad/i', $ua)) $os = 'iPad (iOS)';
  elseif (preg_match('/Android[ ;]*([0-9.]+)?/i', $ua, $m)) {
    $os = 'Android' . (!empty($m[1]) ? ' ' . $m[1] : '');
    // muitos aparelhos Android trazem o modelo no próprio User Agent
    if (preg_match('/Android[^;]*;\s*([^;)]+?)(?:\s+Build|[);])/i', $ua, $mm)) {
      $model = trim($mm[1]);
      $ignore = ['K', 'wv', 'Mobile']; // placeholders do Chrome com UA reduzido
      if ($model !== '' && !in_array($model, $ignore, true)) $os .= ' · ' . $model;
    }
  }
  elseif (preg_match('/Windows NT/i', $ua)) $os = 'Windows';
  elseif (preg_match('/Mac OS X/i', $ua)) $os = 'Mac';
  elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
  $br = '';
  if (preg_match('/Edg\//i', $ua)) $br = 'Edge';
  elseif (preg_match('/OPR\/|Opera/i', $ua)) $br = 'Opera';
  elseif (preg_match('/Chrome\//i', $ua)) $br = 'Chrome';
  elseif (preg_match('/Firefox\//i', $ua)) $br = 'Firefox';
  elseif (preg_match('/Safari\//i', $ua)) $br = 'Safari';
  return trim($os . ($br ? ' · ' . $br : ''));
}
$device = device_label($ua);

// tipo de conexão informado pelo navegador (4g/wifi/…)
$meta = is_array($in['_meta'] ?? null) ? $in['_meta'] : [];
$connType = clean($meta['conn_type'] ?? '');
$connEff  = clean($meta['conn_effective'] ?? '');
$conn = trim($connType . ($connType && $connEff ? ' / ' : '') . $connEff);
$conn = $conn !== '' ? strtoupper($conn) : '';

// geolocalização por IP (API gratuita ip-api.com — só resolve IPs públicos)
$geo = ['city' => '', 'region' => '', 'country' => '', 'isp' => ''];
if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
  $ctx = stream_context_create(['http' => ['timeout' => 3]]);
  $resp = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,isp&lang=pt-BR", false, $ctx);
  if ($resp) {
    $g = json_decode($resp, true);
    if (($g['status'] ?? '') === 'success') {
      $geo = ['city' => $g['city'] ?? '', 'region' => $g['regionName'] ?? '', 'country' => $g['country'] ?? '', 'isp' => $g['isp'] ?? ''];
    }
  }
}
$extra = json_encode([
  'screen' => clean($meta['screen'] ?? ''),
  'lang'   => clean($meta['lang'] ?? ''),
  'platform' => clean($meta['platform'] ?? ''),
], JSON_UNESCAPED_UNICODE);

// próximo atendente da fila
$att = next_attendant();
if (!$att) { echo json_encode(['ok' => false, 'error' => 'no_attendant']); exit; }

// salva o lead
$st = db()->prepare("INSERT INTO leads
  (nome,whatsapp,localizacao,motivo_uso,dispositivo,internet_atual,ativacao,attendant_id,attendant_name,
   ip,user_agent,device,conn_type,geo_city,geo_region,geo_country,geo_isp,extra)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$st->execute([
  $lead['nome'], $lead['whatsapp'], $lead['localizacao'], $lead['motivo_uso'],
  $lead['dispositivo'], $lead['internet_atual'], $lead['ativacao'],
  $att['id'], $att['name'],
  $ip, $ua, $device, $conn, $geo['city'], $geo['region'], $geo['country'], $geo['isp'], $extra
]);

// monta a mensagem a partir do modelo
$map = [
  '{{nome}}'           => $lead['nome'],
  '{{localizacao}}'    => $lead['localizacao'],
  '{{motivo_uso}}'     => $lead['motivo_uso'],
  '{{internet_atual}}' => $lead['internet_atual'],
  '{{dispositivo}}'    => $lead['dispositivo'],
  '{{ativacao}}'       => $lead['ativacao'],
];
$message = strtr(WHATSAPP_TEMPLATE, $map);

// normaliza o número do atendente (Brasil = prefixo 55)
$num = preg_replace('/\D/', '', $att['whatsapp']);
if (strpos($num, '55') !== 0) $num = '55' . $num;

$url = 'https://wa.me/' . $num . '?text=' . rawurlencode($message);

echo json_encode([
  'ok'            => true,
  'attendant'     => $att['name'],
  'whatsapp_url'  => $url,
]);
