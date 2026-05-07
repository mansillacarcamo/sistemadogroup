<?php
/**
 * combustible.php — Listado de hojas de Distribución de Combustible
 * Filtros: mes, obra, responsable, tipo combustible, estado.
 * Acceso: admin del módulo y responsables (ven solo lo suyo).
 */
require_once 'config.php';
requireAuth();
requireCombustibleAcceso($usuario, $pdo);

$esAdmin = isCombustibleAdmin($usuario, $pdo);

// ── Filtros ──────────────────────────────────────────────
$mes        = $_GET['mes']        ?? date('Y-m');
$obraId     = (int)($_GET['obra_id'] ?? 0);
$respId     = (int)($_GET['resp_id'] ?? 0);
$tipoComb   = $_GET['tipo_combustible'] ?? '';
$estado     = $_GET['estado']     ?? '';

[$anio, $mesNum] = explode('-', $mes);
$inicio = "$mes-01";
$fin    = date('Y-m-t', strtotime($inicio));

$where = ["fecha BETWEEN ? AND ?"];
$params = [$inicio, $fin];

// Si NO es admin, filtrar solo sus hojas
if (!$esAdmin) {
  $resp = getResponsableCombustible($usuario, $pdo);
  if (!$resp) {
    // No es responsable ni admin — no debería estar acá
    header('Location: inicio.php');
    exit;
  }
  $where[] = "(responsable_id = ? OR creado_por = ?)";
  $params[] = $resp['id'];
  $params[] = $usuario['id'];
}

if ($obraId)    { $where[] = "obra_id = ?";          $params[] = $obraId; }
if ($respId && $esAdmin) { $where[] = "responsable_id = ?"; $params[] = $respId; }
if ($tipoComb)  { $where[] = "tipo_combustible = ?"; $params[] = $tipoComb; }
if ($estado)    { $where[] = "estado = ?";           $params[] = $estado; }

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$st = $pdo->prepare("SELECT * FROM combustible_hojas $whereSQL ORDER BY fecha DESC, id DESC");
$st->execute($params);
$hojas = $st->fetchAll(PDO::FETCH_ASSOC);

// Listas para filtros
$obras = $pdo->query("SELECT id, codigo, nombre FROM obras ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$responsables = $pdo->query("SELECT id, nombre, obra_nombre FROM combustible_responsables WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Resumen del mes
$resumen = ['total_hojas' => count($hojas), 'total_litros' => 0, 'diesel' => 0, 'gasolina' => 0];
foreach ($hojas as $h) {
  $resumen['total_litros'] += (float)$h['total_litros'];
  if ($h['tipo_combustible'] === 'diesel')   $resumen['diesel']   += (float)$h['total_litros'];
  if ($h['tipo_combustible'] === 'gasolina') $resumen['gasolina'] += (float)$h['total_litros'];
}

// ── Datos para gráficos del mes (respeta los mismos filtros que la tabla) ──
$wsqlChart = implode(' AND ', $where);

// Evolución diaria de litros del mes
$stEvol = $pdo->prepare("SELECT fecha, SUM(total_litros) tot
                         FROM combustible_hojas
                         WHERE $wsqlChart
                         GROUP BY fecha ORDER BY fecha");
$stEvol->execute($params);
$evolRows = $stEvol->fetchAll(PDO::FETCH_ASSOC);

// Diesel vs Gasolina (litros)
$stPie = $pdo->prepare("SELECT tipo_combustible, SUM(total_litros) tot
                        FROM combustible_hojas
                        WHERE $wsqlChart
                        GROUP BY tipo_combustible");
$stPie->execute($params);
$pieRows = $stPie->fetchAll(PDO::FETCH_ASSOC);

// Top 5 obras del mes
$stObras = $pdo->prepare("SELECT COALESCE(NULLIF(obra_nombre,''),'(sin obra)') obra, SUM(total_litros) tot
                          FROM combustible_hojas
                          WHERE $wsqlChart
                          GROUP BY obra_nombre ORDER BY tot DESC LIMIT 5");
$stObras->execute($params);
$obrasTopRows = $stObras->fetchAll(PDO::FETCH_ASSOC);

// Top 5 patentes/equipos del mes (el WHERE necesita prefijar columnas con h.)
$wsqlEq = preg_replace('/\b(fecha|responsable_id|creado_por|obra_id|tipo_combustible|estado)\b/', 'h.$1', $wsqlChart);
$stEq = $pdo->prepare("SELECT COALESCE(NULLIF(v.patente,''),'(sin patente)') patente,
                              SUM(v.cantidad_litros) tot
                       FROM combustible_vales v
                       JOIN combustible_hojas h ON h.id = v.hoja_id
                       WHERE $wsqlEq AND v.cantidad_litros > 0
                       GROUP BY v.patente ORDER BY tot DESC LIMIT 5");
$stEq->execute($params);
$equiposTopRows = $stEq->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver</a>


<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h3 class="mb-0"><i class="bi bi-fuel-pump-fill text-warning me-2"></i>Distribución de Combustible</h3>
  <div class="d-flex gap-2">
    <a href="combustible_hoja_nueva.php" class="btn btn-warning text-dark fw-semibold">
      <i class="bi bi-plus-circle me-1"></i>Nueva hoja
    </a>
    <?php if ($esAdmin): ?>
    <a href="admin_combustible.php" class="btn btn-outline-secondary">
      <i class="bi bi-gear me-1"></i>Administración
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_GET['error']) && $_GET['error']==='sin_permiso'): ?>
  <div class="alert alert-danger">No tienes permisos para esa acción.</div>
<?php endif; ?>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Hojas del mes</div>
        <div class="fs-3 fw-bold"><?= $resumen['total_hojas'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Total litros</div>
        <div class="fs-3 fw-bold"><?= formatCLP($resumen['total_litros'], 2) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #d97706 !important">
      <div class="card-body">
        <div class="text-muted small">Diesel</div>
        <div class="fs-4 fw-bold"><?= formatCLP($resumen['diesel'], 2) ?> L</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd !important">
      <div class="card-body">
        <div class="text-muted small">Gasolina</div>
        <div class="fs-4 fw-bold"><?= formatCLP($resumen['gasolina'], 2) ?> L</div>
      </div>
    </div>
  </div>
</div>

<!-- ╭───────────── GRÁFICOS DEL MES ─────────────╮ -->
<div class="row g-3 mb-3">
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-graph-up text-warning me-2"></i>Evolución diaria — Litros</h6>
        <canvas id="chartEvol" height="90"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Diésel vs Gasolina</h6>
        <canvas id="chartTipo" height="180"></canvas>
      </div>
    </div>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-trophy-fill text-success me-2"></i>Top 5 obras del mes</h6>
        <canvas id="chartObras" height="160"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-truck text-info me-2"></i>Top 5 equipos / patentes</h6>
        <canvas id="chartEquipos" height="160"></canvas>
      </div>
    </div>
  </div>
</div>
<!-- ╰────────────────────────────────────────────╯ -->

<!-- Banner instalar app (solo móvil, se oculta si ya está instalado) -->
<div id="pwaInstallBanner" class="alert alert-warning d-none d-md-none align-items-center justify-content-between" style="border-radius:12px">
  <div>
    <strong><i class="bi bi-phone me-1"></i>Instalar DOGroup en tu teléfono</strong>
    <div class="small text-muted">Acceso directo, sin abrir el navegador</div>
  </div>
  <button id="btnInstallPwa" class="btn btn-warning fw-bold ms-2"><i class="bi bi-download me-1"></i>Instalar</button>
</div>

<!-- Filtros -->
<form method="get" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-12 col-md-2">
        <label class="form-label small mb-1">Mes</label>
        <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1">Obra</label>
        <select name="obra_id" class="form-select form-select-sm">
          <option value="0">— Todas —</option>
          <?php foreach ($obras as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $obraId==$o['id']?'selected':'' ?>>
              <?= htmlspecialchars(($o['codigo']?$o['codigo'].' · ':'').$o['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($esAdmin): ?>
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1">Responsable</label>
        <select name="resp_id" class="form-select form-select-sm">
          <option value="0">— Todos —</option>
          <?php foreach ($responsables as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $respId==$r['id']?'selected':'' ?>>
              <?= htmlspecialchars($r['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Combustible</label>
        <select name="tipo_combustible" class="form-select form-select-sm">
          <option value="">— Todos —</option>
          <option value="diesel"   <?= $tipoComb==='diesel'?'selected':'' ?>>Diesel</option>
          <option value="gasolina" <?= $tipoComb==='gasolina'?'selected':'' ?>>Gasolina</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Estado</label>
        <select name="estado" class="form-select form-select-sm">
          <option value="">— Todos —</option>
          <option value="abierta" <?= $estado==='abierta'?'selected':'' ?>>Abierta</option>
          <option value="cerrada" <?= $estado==='cerrada'?'selected':'' ?>>Cerrada</option>
        </select>
      </div>
      <div class="col-12 d-flex justify-content-end gap-2">
        <a href="combustible.php" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        <button class="btn btn-sm btn-dark"><i class="bi bi-funnel me-1"></i>Filtrar</button>
      </div>
    </div>
  </div>
</form>

<!-- Tabla -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($hojas)): ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No hay hojas en este filtro.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>Obra</th>
            <th>Responsable</th>
            <th>Tipo</th>
            <th>Fuente</th>
            <th class="text-end">Total Litros</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($hojas as $h): ?>
          <tr>
            <td><?= date('d/m/Y', strtotime($h['fecha'])) ?></td>
            <td><?= htmlspecialchars($h['obra_nombre'] ?: '—') ?></td>
            <td><?= htmlspecialchars($h['responsable_nombre'] ?: '—') ?></td>
            <td>
              <?php if ($h['tipo_combustible']==='diesel'): ?>
                <span class="badge bg-warning text-dark">Diesel</span>
              <?php else: ?>
                <span class="badge bg-primary">Gasolina</span>
              <?php endif; ?>
            </td>
            <td><span class="text-muted small"><?= ucfirst($h['tipo_fuente']) ?></span></td>
            <td class="text-end fw-semibold"><?= formatCLP($h['total_litros'], 2) ?> L</td>
            <td>
              <?php if ($h['estado']==='cerrada'): ?>
                <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>Cerrada</span>
              <?php else: ?>
                <span class="badge bg-secondary"><i class="bi bi-pencil me-1"></i>Abierta</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="combustible_hoja_ver.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-dark" title="Ver/editar">
                <i class="bi bi-eye"></i>
              </a>
              <a href="combustible_export_excel.php?hoja_id=<?= $h['id'] ?>"
                 class="btn btn-sm btn-outline-success" title="Exportar Excel de esta hoja">
                <i class="bi bi-file-earmark-excel-fill"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Acción: exportar todas las hojas filtradas -->
<?php if (!empty($hojas)): ?>
<div class="d-flex justify-content-end mt-3">
  <a href="combustible_export_excel.php?<?= http_build_query(array_filter([
        'mes'=>$mes,'obra_id'=>$obraId?:null,'resp_id'=>$respId?:null,
        'tipo_combustible'=>$tipoComb?:null,'estado'=>$estado?:null,'multi'=>1
      ])) ?>"
     class="btn btn-success">
    <i class="bi bi-file-earmark-excel-fill me-1"></i>
    Exportar Excel · <?= count($hojas) ?> hoja(s) del filtro
  </a>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<!-- Chart.js para gráficos del módulo -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const fmt = n => new Intl.NumberFormat('es-CL').format(Math.round(n));
  const dorado = '#d97706', oscuro = '#1a1a2e', azul = '#0d6efd';

  const evol = <?= json_encode($evolRows ?: []) ?>;
  const pie  = <?= json_encode($pieRows ?: []) ?>;
  const top  = <?= json_encode($obrasTopRows ?: []) ?>;
  const eq   = <?= json_encode($equiposTopRows ?: []) ?>;

  // Evolución diaria
  if (document.getElementById('chartEvol')) {
    new Chart(document.getElementById('chartEvol'), {
      type: 'line',
      data: {
        labels: evol.map(r => {
          const d = new Date(r.fecha + 'T00:00:00');
          return d.toLocaleDateString('es-CL',{day:'2-digit',month:'2-digit'});
        }),
        datasets: [{
          label: 'Litros',
          data: evol.map(r => +r.tot),
          borderColor: dorado,
          backgroundColor: 'rgba(217,119,6,.15)',
          borderWidth: 2.5, fill: true, tension: .3,
          pointBackgroundColor: dorado, pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmt(c.parsed.y) + ' L' } } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => fmt(v) } } }
      }
    });
  }

  // Diésel vs Gasolina
  if (document.getElementById('chartTipo')) {
    new Chart(document.getElementById('chartTipo'), {
      type: 'doughnut',
      data: {
        labels: pie.map(r => r.tipo_combustible.charAt(0).toUpperCase() + r.tipo_combustible.slice(1)),
        datasets: [{
          data: pie.map(r => +r.tot),
          backgroundColor: [dorado, azul],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: c => c.label + ': ' + fmt(c.parsed) + ' L' } }
        }
      }
    });
  }

  // Top obras
  if (document.getElementById('chartObras')) {
    new Chart(document.getElementById('chartObras'), {
      type: 'bar',
      data: {
        labels: top.map(r => r.obra),
        datasets: [{ data: top.map(r => +r.tot), backgroundColor: oscuro, borderRadius: 6 }]
      },
      options: {
        indexAxis: 'y', responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmt(c.parsed.x) + ' L' } } },
        scales: { x: { ticks: { callback: v => fmt(v) } } }
      }
    });
  }

  // Top equipos
  if (document.getElementById('chartEquipos')) {
    new Chart(document.getElementById('chartEquipos'), {
      type: 'bar',
      data: {
        labels: eq.map(r => r.patente),
        datasets: [{ data: eq.map(r => +r.tot), backgroundColor: azul, borderRadius: 6 }]
      },
      options: {
        indexAxis: 'y', responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmt(c.parsed.x) + ' L' } } },
        scales: { x: { ticks: { callback: v => fmt(v) } } }
      }
    });
  }

  // ── PWA: capturar evento de instalación ──
  let deferredPrompt = null;
  const banner = document.getElementById('pwaInstallBanner');
  const btn    = document.getElementById('btnInstallPwa');

  // Registrar service worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(()=>{});
  }

  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    if (banner) banner.classList.remove('d-none');
  });

  if (btn) {
    btn.addEventListener('click', async () => {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      deferredPrompt = null;
      if (banner) banner.classList.add('d-none');
    });
  }

  // Si ya está instalado, ocultar banner
  window.addEventListener('appinstalled', () => {
    if (banner) banner.classList.add('d-none');
  });
})();
</script>
