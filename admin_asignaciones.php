<?php
require_once 'config.php';
requireRol('admin');

$p = periodoActual();
$anio = (int)($_GET['anio'] ?? $p['anio']);
$mes  = (int)($_GET['mes']  ?? $p['mes']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (($_POST['monto'] ?? []) as $uid => $monto) {
        $monto = parseMonto($monto);
        $car = parseMonto($_POST['carry'][$uid] ?? 0);
        $obs = trim($_POST['obs'][$uid] ?? '');
        $q = $pdo->prepare("SELECT id FROM asignaciones WHERE usuario_id=? AND anio=? AND mes=?");
        $q->execute([$uid,$anio,$mes]);
        if ($id = $q->fetchColumn()) {
            $pdo->prepare("UPDATE asignaciones SET monto_asignado=?, monto_carryover=?, observaciones=?, asignado_por=? WHERE id=?")
                ->execute([$monto,$car,$obs,$_SESSION['user_id'],$id]);
        } elseif ($monto > 0 || $car > 0) {
            $pdo->prepare("INSERT INTO asignaciones (usuario_id,anio,mes,monto_asignado,monto_carryover,observaciones,asignado_por) VALUES (?,?,?,?,?,?,?)")
                ->execute([$uid,$anio,$mes,$monto,$car,$obs,$_SESSION['user_id']]);
        }
    }
    flash('exito','Asignaciones guardadas.');
    header('Location: admin_asignaciones.php?anio='.$anio.'&mes='.$mes); exit;
}

$users = $pdo->query("SELECT u.id,u.nombre,u.email,u.zona,u.cargo FROM usuarios u WHERE u.rol='usuario' AND u.activo=1 ORDER BY u.nombre")->fetchAll();

$titulo = 'Asignaciones';
include 'includes/head.php';
include 'includes/nav.php';
?>
<a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver</a>


<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Asignaciones · <?= nombreMes($mes) ?> <?= $anio ?></h4>
  <form class="d-flex gap-2" method="get">
    <select name="mes" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m===$mes?'selected':'' ?>><?= nombreMes($m) ?></option><?php endfor; ?>
    </select>
    <select name="anio" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php for($y=date('Y')+1;$y>=date('Y')-2;$y--): ?><option value="<?= $y ?>" <?= $y===$anio?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
    </select>
  </form>
</div>

<form method="post">
<div class="card"><div class="card-body p-0">
<div class="table-responsive">
<table class="table table-sm table-hover align-middle mb-0">
  <thead class="table-light">
    <tr><th>Usuario</th><th>Zona</th><th>Asignado</th><th>Arrastre</th><th>Gastado</th><th>Saldo</th><th>Observación</th></tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u):
    $a = asignacionPeriodo($pdo, $u['id'], $anio, $mes);
    $g = totalGastadoPeriodo($pdo, $u['id'], $anio, $mes);
    $s = ($a['total'] ?? 0) - $g;
    $obs = '';
    $qo = $pdo->prepare("SELECT observaciones FROM asignaciones WHERE usuario_id=? AND anio=? AND mes=?");
    $qo->execute([$u['id'],$anio,$mes]);
    $obs = $qo->fetchColumn() ?: '';
  ?>
    <tr>
      <td><div class="fw-semibold"><?= h($u['nombre']) ?></div><div class="small text-muted"><?= h($u['email']) ?></div></td>
      <td class="small"><?= h($u['zona']?:'—') ?></td>
      <td><div class="input-group input-group-sm"><span class="input-group-text">$</span>
        <input type="text" name="monto[<?= $u['id'] ?>]" class="form-control input-clp" value="<?= number_format($a['monto_asignado']??0,0,',','.') ?>"></div></td>
      <td><div class="input-group input-group-sm"><span class="input-group-text">$</span>
        <input type="text" name="carry[<?= $u['id'] ?>]" class="form-control input-clp" value="<?= number_format($a['monto_carryover']??0,0,',','.') ?>"></div></td>
      <td class="text-danger"><?= fmtCLP($g) ?></td>
      <td class="<?= $s<0?'text-danger':'text-success' ?> fw-semibold"><?= fmtCLP($s) ?></td>
      <td><input type="text" name="obs[<?= $u['id'] ?>]" class="form-control form-control-sm" value="<?= h($obs) ?>"></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div></div>

<div class="mt-3 d-flex justify-content-end">
  <button class="btn btn-primary btn-lg"><i class="bi bi-check-circle me-1"></i> Guardar asignaciones</button>
</div>
</form>

<?php include 'includes/foot.php'; ?>
