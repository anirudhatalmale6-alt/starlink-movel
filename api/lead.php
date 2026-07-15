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

// próximo atendente da fila
$att = next_attendant();
if (!$att) { echo json_encode(['ok' => false, 'error' => 'no_attendant']); exit; }

// salva o lead
$st = db()->prepare("INSERT INTO leads
  (nome,whatsapp,localizacao,motivo_uso,dispositivo,internet_atual,ativacao,attendant_id,attendant_name)
  VALUES (?,?,?,?,?,?,?,?,?)");
$st->execute([
  $lead['nome'], $lead['whatsapp'], $lead['localizacao'], $lead['motivo_uso'],
  $lead['dispositivo'], $lead['internet_atual'], $lead['ativacao'],
  $att['id'], $att['name']
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
