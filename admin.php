<?php
/* ============================================================
   Painel Administrativo — Starlink Móvel
   - Gerencia atendentes (fila de WhatsApp)
   - Visualiza os leads capturados
   ============================================================ */
session_start();
require_once __DIR__ . '/api/db.php';

/* ---------- Login ---------- */
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

if (isset($_POST['login_password'])) {
  if (hash_equals(ADMIN_PASSWORD, $_POST['login_password'])) {
    $_SESSION['auth'] = true;
    header('Location: admin.php'); exit;
  } else { $login_error = 'Senha incorreta.'; }
}
$authed = !empty($_SESSION['auth']);

/* ---------- Ações (somente autenticado) ---------- */
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['action'] ?? '';

  if ($a === 'add') {
    $max = (int) db()->query("SELECT COALESCE(MAX(queue_order),0) m FROM attendants")->fetch()['m'];
    $st = db()->prepare("INSERT INTO attendants (name,whatsapp,role,active,queue_order) VALUES (?,?,?,1,?)");
    $st->execute([trim($_POST['name']), trim($_POST['whatsapp']), trim($_POST['role'] ?? ''), $max + 1]);
  }
  elseif ($a === 'update') {
    $st = db()->prepare("UPDATE attendants SET name=?, whatsapp=?, role=? WHERE id=?");
    $st->execute([trim($_POST['name']), trim($_POST['whatsapp']), trim($_POST['role'] ?? ''), (int)$_POST['id']]);
  }
  elseif ($a === 'toggle') {
    db()->prepare("UPDATE attendants SET active = 1 - active WHERE id=?")->execute([(int)$_POST['id']]);
  }
  elseif ($a === 'delete') {
    db()->prepare("DELETE FROM attendants WHERE id=?")->execute([(int)$_POST['id']]);
  }
  elseif ($a === 'move') {
    // troca a ordem com o vizinho (cima/baixo)
    $id = (int)$_POST['id']; $dir = $_POST['dir'] === 'up' ? 'up' : 'down';
    $rows = db()->query("SELECT id,queue_order FROM attendants ORDER BY queue_order ASC, id ASC")->fetchAll();
    for ($i = 0; $i < count($rows); $i++) {
      if ((int)$rows[$i]['id'] === $id) {
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < count($rows)) {
          $u = db()->prepare("UPDATE attendants SET queue_order=? WHERE id=?");
          $u->execute([$rows[$j]['queue_order'], $rows[$i]['id']]);
          $u->execute([$rows[$i]['queue_order'], $rows[$j]['id']]);
        }
        break;
      }
    }
  }
  elseif ($a === 'reset_index') {
    setting_set('round_robin_index', -1);
  }
  elseif ($a === 'delete_lead') {
    db()->prepare("DELETE FROM leads WHERE id=?")->execute([(int)$_POST['id']]);
  }
  header('Location: admin.php' . (isset($_POST['tab']) ? '?tab=' . $_POST['tab'] : '')); exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$tab = $_GET['tab'] ?? 'attendants';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Painel · Starlink Móvel</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#0a0a0b;color:#f4f4f5;font-family:'Inter',system-ui,Arial,sans-serif;line-height:1.5}
  a{color:inherit}
  .wrap{max-width:1040px;margin:0 auto;padding:30px 22px 80px}
  .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:34px;padding-bottom:22px;border-bottom:1px solid rgba(255,255,255,.08)}
  .brand{font-size:18px;font-weight:700}
  .brand small{display:block;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#6b6b72;font-weight:500;margin-top:3px}
  .btn{display:inline-flex;align-items:center;gap:6px;font-family:inherit;font-size:13px;font-weight:600;padding:9px 16px;border-radius:5px;border:1px solid rgba(255,255,255,.14);background:transparent;color:#fff;cursor:pointer;text-decoration:none;transition:.2s}
  .btn:hover{background:rgba(255,255,255,.07)}
  .btn--p{background:#fff;color:#000;border-color:#fff}
  .btn--p:hover{background:#e5e5e5}
  .btn--sm{padding:6px 10px;font-size:12px}
  .btn--danger:hover{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.5);color:#f87171}
  .tabs{display:flex;gap:8px;margin-bottom:26px}
  .tab{padding:10px 18px;border-radius:6px;font-size:14px;font-weight:600;color:#8a8a90;text-decoration:none;transition:.2s}
  .tab.on{background:rgba(255,255,255,.08);color:#fff}
  .card{background:#111113;border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:22px;margin-bottom:20px}
  .card h2{font-size:15px;font-weight:600;margin-bottom:18px}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:13px 12px;border-bottom:1px solid rgba(255,255,255,.07);font-size:13.5px;vertical-align:middle}
  th{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6b6b72;font-weight:600}
  tr:last-child td{border-bottom:none}
  .pill{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}
  .pill--on{background:rgba(34,197,94,.15);color:#4ade80}
  .pill--off{background:rgba(255,255,255,.07);color:#8a8a90}
  .qn{width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.06);border-radius:6px;font-size:12px;font-weight:700;color:#c8c8ce}
  form.inline{display:inline}
  input,select{font-family:inherit;background:#1a1a1d;border:1px solid rgba(255,255,255,.12);color:#fff;border-radius:6px;padding:11px 13px;font-size:14px;width:100%}
  input:focus,select:focus{outline:none;border-color:rgba(255,255,255,.35)}
  label{display:block;font-size:12px;color:#8a8a90;margin-bottom:6px;font-weight:500}
  .grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end}
  .muted{color:#6b6b72;font-size:13px}
  .actions{display:flex;gap:5px;align-items:center;flex-wrap:wrap}
  .login{max-width:360px;margin:14vh auto 0}
  .login .card{padding:30px}
  .err{background:rgba(239,68,68,.12);color:#f87171;padding:10px 13px;border-radius:6px;font-size:13px;margin-bottom:14px}
  .note{font-size:12.5px;color:#6b6b72;margin-top:14px;line-height:1.6}
  .empty{padding:40px;text-align:center;color:#6b6b72;font-size:14px}
  .det{display:grid;grid-template-columns:1fr 1fr;gap:26px;padding:18px 6px}
  .det__h{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6b6b72;font-weight:600;margin-bottom:12px}
  .det__row{display:flex;justify-content:space-between;gap:16px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px}
  .det__row span{color:#7a7a80}
  .det__row b{color:#e8e8ec;text-align:right;max-width:65%}
  @media(max-width:680px){.grid{grid-template-columns:1fr}.hide-sm{display:none}.det{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if (!$authed): ?>
  <div class="login">
    <div class="brand" style="text-align:center;margin-bottom:24px">Starlink Móvel<small>Painel Administrativo</small></div>
    <div class="card">
      <?php if (!empty($login_error)): ?><div class="err"><?= h($login_error) ?></div><?php endif; ?>
      <form method="post">
        <label>Senha de acesso</label>
        <input type="password" name="login_password" autofocus />
        <button class="btn btn--p" style="width:100%;justify-content:center;margin-top:16px" type="submit">Entrar</button>
      </form>
    </div>
  </div>

<?php else:
  $attendants = db()->query("SELECT * FROM attendants ORDER BY queue_order ASC, id ASC")->fetchAll();
  $leads = db()->query("SELECT * FROM leads ORDER BY id DESC LIMIT 500")->fetchAll();
  $rr = (int) setting_get('round_robin_index', -1);
  $activeCount = 0; foreach ($attendants as $x) if ($x['active']) $activeCount++;
  $nextName = '—';
  if ($activeCount > 0) {
    $act = array_values(array_filter($attendants, fn($x) => $x['active']));
    $nextName = $act[($rr + 1) % count($act)]['name'];
  }
?>
  <div class="wrap">
    <div class="top">
      <div class="brand">Starlink Móvel<small>Painel Administrativo</small></div>
      <a class="btn" href="admin.php?logout=1">Sair</a>
    </div>

    <div class="tabs">
      <a class="tab <?= $tab==='attendants'?'on':'' ?>" href="admin.php?tab=attendants">Atendentes (Fila)</a>
      <a class="tab <?= $tab==='leads'?'on':'' ?>" href="admin.php?tab=leads">Leads (<?= count($leads) ?>)</a>
    </div>

  <?php if ($tab === 'attendants'): ?>
    <div class="card">
      <h2>Adicionar atendente</h2>
      <form method="post">
        <input type="hidden" name="action" value="add" />
        <div class="grid">
          <div><label>Nome</label><input name="name" required placeholder="Ex: Carlos" /></div>
          <div><label>WhatsApp (com DDD)</label><input name="whatsapp" required placeholder="Ex: 11 99999-8888" /></div>
          <div><label>Função (opcional)</label><input name="role" placeholder="Ex: Especialista" /></div>
          <div><button class="btn btn--p" type="submit">Adicionar</button></div>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>Fila de atendimento &nbsp;<span class="muted">— próximo lead irá para: <strong style="color:#4ade80"><?= h($nextName) ?></strong></span></h2>
      <?php if (!$attendants): ?>
        <div class="empty">Nenhum atendente cadastrado ainda. Adicione o primeiro acima.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>#</th><th>Nome</th><th class="hide-sm">WhatsApp</th><th class="hide-sm">Função</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($attendants as $i => $at): ?>
          <tr>
            <td><span class="qn"><?= $i+1 ?></span></td>
            <td>
              <form class="inline" method="post" onsubmit="return false" id="edit-<?= $at['id'] ?>"></form>
              <strong><?= h($at['name']) ?></strong>
            </td>
            <td class="hide-sm muted"><?= h($at['whatsapp']) ?></td>
            <td class="hide-sm muted"><?= h($at['role']) ?: '—' ?></td>
            <td>
              <?php if ($at['active']): ?><span class="pill pill--on">Ativo</span>
              <?php else: ?><span class="pill pill--off">Pausado</span><?php endif; ?>
            </td>
            <td>
              <div class="actions">
                <form class="inline" method="post"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $at['id'] ?>"><input type="hidden" name="dir" value="up"><button class="btn btn--sm" title="Subir na fila">▲</button></form>
                <form class="inline" method="post"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $at['id'] ?>"><input type="hidden" name="dir" value="down"><button class="btn btn--sm" title="Descer na fila">▼</button></form>
                <form class="inline" method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $at['id'] ?>"><button class="btn btn--sm"><?= $at['active']?'Pausar':'Ativar' ?></button></form>
                <button class="btn btn--sm" onclick="editRow(<?= $at['id'] ?>)">Editar</button>
                <form class="inline" method="post" onsubmit="return confirm('Remover este atendente?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $at['id'] ?>"><button class="btn btn--sm btn--danger">Excluir</button></form>
              </div>
              <div id="editform-<?= $at['id'] ?>" style="display:none;margin-top:12px">
                <form method="post" class="grid">
                  <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $at['id'] ?>">
                  <div><label>Nome</label><input name="name" value="<?= h($at['name']) ?>" required></div>
                  <div><label>WhatsApp</label><input name="whatsapp" value="<?= h($at['whatsapp']) ?>" required></div>
                  <div><label>Função</label><input name="role" value="<?= h($at['role']) ?>"></div>
                  <div><button class="btn btn--p" type="submit">Salvar</button></div>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="note">
        Os leads são distribuídos em fila (round-robin): o 1º vai para o nº 1, o 2º para o nº 2, e assim por diante, voltando ao início.
        Apenas atendentes <strong>Ativos</strong> entram na fila. Use ▲ ▼ para mudar a ordem.
        <form class="inline" method="post"><input type="hidden" name="action" value="reset_index"><button class="btn btn--sm" style="margin-left:8px">Reiniciar fila do zero</button></form>
      </div>
      <?php endif; ?>
    </div>

  <?php else: /* ===== LEADS ===== */ ?>
    <div class="card">
      <h2>Leads capturados &nbsp;<span class="muted">— clique em "Detalhes" para ver IP, geolocalização, dispositivo e conexão</span></h2>
      <?php if (!$leads): ?>
        <div class="empty">Nenhum lead capturado ainda.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Data / Hora</th><th>Nome</th><th>WhatsApp</th><th class="hide-sm">Localização</th><th class="hide-sm">Cenário</th><th>Atendente</th><th></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($leads as $ld):
          $geoParts = array_filter([$ld['geo_city'], $ld['geo_region'], $ld['geo_country']]);
          $geo = $geoParts ? implode(', ', $geoParts) : '—';
          $ex = json_decode($ld['extra'] ?? '', true) ?: [];
        ?>
          <tr>
            <td class="muted" style="white-space:nowrap"><?= h(date('d/m/Y H:i', strtotime($ld['created_at']))) ?></td>
            <td><strong><?= h($ld['nome']) ?></strong></td>
            <td><a href="https://wa.me/55<?= preg_replace('/\D/','',$ld['whatsapp']) ?>" target="_blank" style="color:#4ade80;text-decoration:none">+55 <?= h($ld['whatsapp']) ?></a></td>
            <td class="hide-sm muted"><?= h($ld['localizacao']) ?></td>
            <td class="hide-sm muted"><?= h($ld['motivo_uso']) ?></td>
            <td class="muted"><?= h($ld['attendant_name']) ?></td>
            <td><button class="btn btn--sm" onclick="toggleDet(<?= $ld['id'] ?>)">Detalhes</button></td>
            <td><form class="inline" method="post" onsubmit="return confirm('Excluir este lead?')"><input type="hidden" name="action" value="delete_lead"><input type="hidden" name="tab" value="leads"><input type="hidden" name="id" value="<?= $ld['id'] ?>"><button class="btn btn--sm btn--danger">✕</button></form></td>
          </tr>
          <tr id="det-<?= $ld['id'] ?>" style="display:none">
            <td colspan="8" style="background:rgba(255,255,255,.02)">
              <div class="det">
                <div class="det__col">
                  <div class="det__h">Respostas do quiz</div>
                  <div class="det__row"><span>Nome</span><b><?= h($ld['nome']) ?></b></div>
                  <div class="det__row"><span>WhatsApp</span><b>+55 <?= h($ld['whatsapp']) ?></b></div>
                  <div class="det__row"><span>Localização (informada)</span><b><?= h($ld['localizacao']) ?: '—' ?></b></div>
                  <div class="det__row"><span>Cenário de uso</span><b><?= h($ld['motivo_uso']) ?: '—' ?></b></div>
                  <div class="det__row"><span>Dispositivo (quiz)</span><b><?= h($ld['dispositivo']) ?: '—' ?></b></div>
                  <div class="det__row"><span>Internet atual</span><b><?= h($ld['internet_atual']) ?: '—' ?></b></div>
                  <div class="det__row"><span>Ativação</span><b><?= h($ld['ativacao']) ?: '—' ?></b></div>
                </div>
                <div class="det__col">
                  <div class="det__h">Dados técnicos do contato</div>
                  <div class="det__row"><span>Data e hora exatas</span><b><?= h(date('d/m/Y H:i:s', strtotime($ld['created_at']))) ?></b></div>
                  <div class="det__row"><span>Endereço IP</span><b><?= h($ld['ip']) ?: '—' ?></b></div>
                  <div class="det__row"><span>Geolocalização (IP)</span><b><?= h($geo) ?></b></div>
                  <div class="det__row"><span>Provedor (ISP)</span><b><?= h($ld['geo_isp']) ?: '—' ?></b></div>
                  <div class="det__row"><span>Dispositivo (User Agent)</span><b><?= h($ld['device']) ?: '—' ?></b></div>
                  <div class="det__row"><span>Tipo de conexão</span><b><?= h($ld['conn_type']) ?: '—' ?></b></div>
                  <?php if (!empty($ex['screen'])): ?><div class="det__row"><span>Tela / Idioma</span><b><?= h($ex['screen']) ?> · <?= h($ex['lang'] ?? '') ?></b></div><?php endif; ?>
                  <div class="det__row"><span>User Agent completo</span><b style="font-weight:400;font-size:11.5px;word-break:break-all"><?= h($ld['user_agent']) ?: '—' ?></b></div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="note">Obs.: a geolocalização e o provedor são obtidos pelo IP público do visitante. O "tipo de conexão" (4G/5G/WiFi) é informado pelo navegador quando disponível — alguns navegadores (ex.: Safari no iPhone) não expõem esse dado por privacidade; nesses casos aparece "—". O modelo exato do aparelho não é exposto pelos navegadores, por isso guardamos o User Agent completo e o sistema/navegador identificados.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  </div>

  <script>
    function editRow(id){
      var el = document.getElementById('editform-'+id);
      el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
    function toggleDet(id){
      var el = document.getElementById('det-'+id);
      el.style.display = el.style.display === 'none' ? 'table-row' : 'none';
    }
  </script>
<?php endif; ?>
</body>
</html>
