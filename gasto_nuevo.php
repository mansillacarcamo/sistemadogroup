<?php
require_once 'config.php';
requireAuth();

$uid = (int)$_SESSION['user_id'];
$cats = $pdo->query("SELECT nombre FROM categorias_gasto WHERE activo=1 ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto       = parseMonto($_POST['monto'] ?? '');
    $monto_peaje = parseMonto($_POST['monto_peaje'] ?? '0');
    $fecha       = $_POST['fecha'] ?? date('Y-m-d');
    $cat         = trim($_POST['categoria'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $proveedor   = trim($_POST['proveedor'] ?? '');
    $numdoc      = trim($_POST['numero_documento'] ?? '');
    $tipodoc     = $_POST['tipo_documento'] ?? 'boleta';
    $obra_id     = (int)($_POST['obra_id'] ?? 0) ?: null;
    $obra_nombre = '';
    if ($obra_id) {
        $stObra = $pdo->prepare("SELECT nombre FROM obras_faena WHERE id=?");
        $stObra->execute([$obra_id]);
        $obra_nombre = $stObra->fetchColumn() ?: '';
    }

    if ($monto <= 0) { flash('error','El monto debe ser mayor a 0.'); header('Location: gasto_nuevo.php'); exit; }
    if (!$fecha)     { flash('error','Indica la fecha del gasto.');   header('Location: gasto_nuevo.php'); exit; }

    $pdo->prepare("INSERT INTO gastos (usuario_id,fecha,monto,monto_peaje,obra_id,obra_nombre,categoria,descripcion,proveedor,numero_documento,tipo_documento,estado)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?, 'registrado')")
        ->execute([$uid,$fecha,$monto,$monto_peaje,$obra_id,$obra_nombre,$cat,$descripcion,$proveedor,$numdoc,$tipodoc]);
    $gid = (int)$pdo->lastInsertId();

    if (!empty($_FILES['archivos']['name'][0])) {
        $dir = __DIR__ . '/uploads/u' . $uid;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $cnt = count($_FILES['archivos']['name']);
        for ($i=0; $i<$cnt; $i++) {
            if ($_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $orig = $_FILES['archivos']['name'][$i];
            $tmp  = $_FILES['archivos']['tmp_name'][$i];
            $type = $_FILES['archivos']['type'][$i];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','heic','heif','pdf'];
            if (!in_array($ext, $allowed)) continue;
            $nuevo = 'g' . $gid . '_' . time() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($tmp, $dir . '/' . $nuevo)) {
                $pdo->prepare("INSERT INTO archivos_gasto (gasto_id,nombre_archivo,nombre_original,tipo) VALUES (?,?,?,?)")
                    ->execute([$gid, 'u'.$uid.'/'.$nuevo, $orig, $type]);
            }
        }
    }

    flash('exito','Gasto registrado correctamente.');
    header('Location: mis_gastos.php');
    exit;
}

$mu = $pdo->prepare("SELECT nombre, usuario, ciudad, region, zona, cargo, foto_perfil FROM usuarios WHERE id=?");
$mu->execute([$uid]); $mu = $mu->fetch();

$titulo = 'Registrar gasto';
include 'includes/head.php';
include 'includes/nav.php';
?>
<a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver</a>


<div class="row justify-content-center">
  <div class="col-12 col-lg-8">
    <div class="card mb-3 border-0 bg-light">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <?php $urlFotoGn = urlFotoUsuario($mu['foto_perfil'] ?? ''); ?>
        <?php if ($urlFotoGn): ?>
          <img src="<?= h($urlFotoGn) ?>" alt="<?= h($mu['nombre']) ?>" class="tecnico-avatar-sm" style="object-fit:cover;">
        <?php else: ?>
          <div class="tecnico-avatar-sm"><?= strtoupper(mb_substr($mu['nombre'] ?: 'U',0,1)) ?></div>
        <?php endif; ?>
        <div class="flex-grow-1">
          <div class="fw-bold small"><?= h($mu['nombre']) ?></div>
          <div class="small text-muted">
            <?php if ($mu['region']): ?><i class="bi bi-geo-alt-fill"></i> <?= h($mu['region']) ?><?php endif; ?>
            <?php if ($mu['ciudad']): ?> - <?= h($mu['ciudad']) ?><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <style>.tecnico-avatar-sm{ width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#0d6efd,#6610f2); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; }</style>

    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle me-1"></i>Registrar nuevo gasto</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" autocomplete="off">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Monto (CLP) *</label>
              <div class="input-group input-group-lg">
                <span class="input-group-text">$</span>
                <input type="text" name="monto" class="form-control input-clp" inputmode="numeric" required placeholder="0">
              </div>
              <div class="form-text">Ingresa el monto total de la boleta.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Peaje (CLP)</label>
              <div class="input-group input-group-lg">
                <span class="input-group-text"><i class="bi bi-signpost-2-fill"></i></span>
                <input type="text" name="monto_peaje" id="montoPeaje" class="form-control input-clp" inputmode="numeric" placeholder="0" value="0">
              </div>
              <div class="form-text">Monto de peajes asociados al gasto.</div>
            </div>

            <div class="col-12">
              <div class="alert alert-info py-2 px-3 d-flex align-items-center gap-2 mb-0" id="totalGasto" style="display:none!important">
                <i class="bi bi-calculator-fill"></i>
                <span>Total (gasto + peaje): <strong id="totalGastoVal">$0</strong></span>
              </div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Fecha *</label>
              <input type="date" name="fecha" class="form-control form-control-lg" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold"><i class="bi bi-building me-1"></i>ID de Obra</label>
              <?php
                $obrasDisp = [];
                try { $obrasDisp = $pdo->query("SELECT id, codigo, nombre FROM obras_faena WHERE estado='activa' ORDER BY codigo")->fetchAll(); } catch(Exception $e) {}
              ?>
              <?php if (!empty($obrasDisp)): ?>
              <select name="obra_id" class="form-select form-select-lg">
                <option value="">— Sin obra asignada —</option>
                <?php foreach ($obrasDisp as $ob): ?>
                <option value="<?= $ob['id'] ?>"><?= htmlspecialchars($ob['codigo'].' — '.$ob['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php else: ?>
              <input type="text" name="obra_id" class="form-control" placeholder="ID de obra (número)" value="">
              <div class="form-text text-muted">Ingresa el ID o código de la obra.</div>
              <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Categoría</label>
              <select name="categoria" class="form-select form-select-lg">
                <option value="">— Seleccionar —</option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?= htmlspecialchars($c['nombre']) ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Tipo documento</label>
              <select name="tipo_documento" class="form-select form-select-lg">
                <option value="boleta">Boleta</option>
                <option value="factura">Factura</option>
                <option value="ticket">Ticket</option>
                <option value="otro">Otro</option>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Proveedor / Comercio</label>
              <input type="text" name="proveedor" class="form-control" placeholder="Ej. Copec, Líder...">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">N° Documento</label>
              <input type="text" name="numero_documento" class="form-control" placeholder="N° boleta/factura">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Descripción / Detalle</label>
              <textarea name="descripcion" class="form-control" rows="2" placeholder="¿En qué se gastó? (opcional)"></textarea>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">
                <i class="bi bi-camera me-1"></i>Foto de la boleta
              </label>
              <div class="dropzone">
                <input type="file" name="archivos[]" accept="image/*,application/pdf" capture="environment"
                       multiple class="form-control" data-preview="#preview">
                <div class="small text-muted mt-2">
                  Toca para elegir o tomar una foto desde tu cámara. Puedes adjuntar varias.
                </div>
              </div>
              <div id="preview" class="mt-2 d-flex flex-wrap"></div>
            </div>
          </div>

          <hr class="my-4">
          <div class="d-flex gap-2">
            <button class="btn btn-primary btn-lg flex-grow-1">
              <i class="bi bi-check-circle me-1"></i> Guardar gasto
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/foot.php'; ?>
<script>
// Calcular total gasto + peaje en tiempo real
function parseMontoCLP(v){ return parseFloat((v||'0').replace(/\./g,'').replace(',','.')) || 0; }
function formatCLP(n){ return '$'+Math.round(n).toLocaleString('es-CL'); }
function actualizarTotal() {
  var monto = parseMontoCLP(document.querySelector('[name="monto"]')?.value || '0');
  var peaje = parseMontoCLP(document.querySelector('[name="monto_peaje"]')?.value || '0');
  var total = monto + peaje;
  var box   = document.getElementById('totalGasto');
  var val   = document.getElementById('totalGastoVal');
  if (box && val) {
    box.style.display = (peaje > 0) ? 'flex' : 'none';
    val.textContent = formatCLP(total);
  }
}
document.querySelectorAll('[name="monto"],[name="monto_peaje"]').forEach(function(el){
  el.addEventListener('input', actualizarTotal);
});
</script>
