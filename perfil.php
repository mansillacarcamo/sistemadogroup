<?php
require_once 'config.php';
requireAuth();
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE usuarios SET nombre=?, telefono=?, rut=?, cargo=?, zona=?, ciudad=?, region=? WHERE id=?")
        ->execute([
            trim($_POST['nombre'] ?? ''),
            trim($_POST['telefono'] ?? ''),
            trim($_POST['rut'] ?? ''),
            trim($_POST['cargo'] ?? ''),
            trim($_POST['zona'] ?? ''),
            trim($_POST['ciudad'] ?? ''),
            trim($_POST['region'] ?? ''),
            $uid
        ]);

    // Foto de perfil (tamaño carnet)
    try {
        $fotoActual = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id=?");
        $fotoActual->execute([$uid]);
        $fotoActual = $fotoActual->fetchColumn();
        $nuevaFoto = procesarFotoPerfil('foto_perfil', $uid, $fotoActual);
        if ($nuevaFoto) {
            $pdo->prepare("UPDATE usuarios SET foto_perfil=? WHERE id=?")->execute([$nuevaFoto, $uid]);
            flash('exito','Foto de perfil actualizada.');
        }
    } catch (Exception $e) {
        flash('error', $e->getMessage());
    }

    if (!empty($_POST['nueva_clave'])) {
        if ($_POST['nueva_clave'] !== ($_POST['clave_rep'] ?? '')) {
            flash('error','Las contraseñas no coinciden.');
        } else {
            $pdo->prepare("UPDATE usuarios SET clave=? WHERE id=?")
                ->execute([password_hash($_POST['nueva_clave'],PASSWORD_DEFAULT),$uid]);
            flash('exito','Contraseña actualizada.');
        }
    }
    $_SESSION['user_nombre'] = trim($_POST['nombre'] ?? '');
    flash('exito','Perfil actualizado.');
    header('Location: perfil.php'); exit;
}
$u = $pdo->prepare("SELECT * FROM usuarios WHERE id=?"); $u->execute([$uid]); $u = $u->fetch();

// Asignacion del periodo actual (solo lectura: la asigna el Administrador)
$p = periodoActual();
$asig = asignacionPeriodo($pdo, $uid, $p['anio'], $p['mes']);
$gastadoAct = totalGastadoPeriodo($pdo, $uid, $p['anio'], $p['mes']);
$saldoAct = ($asig['total'] ?? 0) - $gastadoAct;

$titulo = 'Mi perfil';
include 'includes/head.php'; include 'includes/nav.php';
?>
<a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver</a>

<div class="row justify-content-center"><div class="col-12 col-lg-7">

<div class="card mb-3 border-0 shadow-sm">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-cash-coin me-1 text-success"></i>Monto asignado - <?= nombreMes($p['mes']) ?> <?= $p['anio'] ?></span>
    <span class="badge bg-secondary"><i class="bi bi-lock-fill"></i> Solo lectura</span>
  </div>
  <div class="card-body">
    <div class="row g-2 text-center">
      <div class="col-4">
        <div class="text-muted small text-uppercase">Asignado</div>
        <div class="fw-bold fs-5 text-nowrap"><?= fmtCLP($asig['total'] ?? 0) ?></div>
      </div>
      <div class="col-4">
        <div class="text-muted small text-uppercase">Gastado</div>
        <div class="fw-bold fs-5 text-danger text-nowrap"><?= fmtCLP($gastadoAct) ?></div>
      </div>
      <div class="col-4">
        <div class="text-muted small text-uppercase">Saldo</div>
        <div class="fw-bold fs-5 text-nowrap text-<?= $saldoAct<0?'danger':'success' ?>"><?= fmtCLP($saldoAct) ?></div>
      </div>
    </div>
    <div class="alert alert-info small mt-3 mb-0 py-2">
      <i class="bi bi-info-circle-fill"></i>
      El monto asignado es establecido por el Administrador. Si necesitas un ajuste, solicitalo a tu Jefe Zonal.
    </div>
  </div>
</div>

<div class="card"><div class="card-header"><i class="bi bi-person-circle me-1"></i>Mi perfil</div>
<div class="card-body">
<form method="post" enctype="multipart/form-data">
  <!-- Foto de perfil tamaño carnet -->
  <div class="text-center mb-4 pb-3 border-bottom">
    <?php $urlFoto = urlFotoUsuario($u['foto_perfil'] ?? ''); ?>
    <div class="position-relative d-inline-block mb-2">
      <?php if ($urlFoto): ?>
        <img id="previewFoto" src="<?= h($urlFoto) ?>" alt="Foto de perfil"
             style="width:140px;height:180px;object-fit:cover;border:3px solid #e5e7eb;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
      <?php else: ?>
        <div id="previewFoto" class="d-flex align-items-center justify-content-center bg-light text-muted"
             style="width:140px;height:180px;border:3px dashed #cbd5e1;border-radius:8px;">
          <div class="text-center"><i class="bi bi-person-bounding-box" style="font-size:3rem;"></i><div class="small mt-1">Sin foto</div></div>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <label for="fotoInput" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-camera"></i> <?= $urlFoto ? 'Cambiar foto' : 'Subir foto carnet' ?>
      </label>
      <input id="fotoInput" type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" class="d-none">
      <div class="form-text small mt-1">Formato tamaño carnet · JPG/PNG · máx 3 MB</div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-8">
      <label class="form-label">Nombre</label>
      <input name="nombre" class="form-control" value="<?= h($u['nombre']) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Usuario</label>
      <input class="form-control" value="<?= h($u['usuario']) ?>" disabled>
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input class="form-control" value="<?= h($u['email']) ?>" disabled>
    </div>
    <div class="col-md-6">
      <label class="form-label">Teléfono</label>
      <input name="telefono" class="form-control" value="<?= h($u['telefono']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">RUT</label>
      <input name="rut" class="form-control" value="<?= h($u['rut']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Region</label>
      <select name="region" class="form-select">
        <option value="">- Seleccionar -</option>
        <?php foreach (regionesChile() as $r): ?>
          <option value="<?= h($r) ?>" <?= ($u['region']??'')===$r?'selected':'' ?>><?= h($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Ciudad</label>
      <input name="ciudad" class="form-control" value="<?= h($u['ciudad']) ?>" placeholder="Ej. Talca, Concepcion...">
    </div>
    <div class="col-md-6">
      <label class="form-label">Zona / Sector</label>
      <input name="zona" class="form-control" value="<?= h($u['zona']) ?>" placeholder="Norte, Sur, Centro...">
    </div>
    <div class="col-md-6">
      <label class="form-label">Cargo</label>
      <input name="cargo" class="form-control" value="<?= h($u['cargo']) ?>">
    </div>
  </div>
  <hr>
  <h6 class="fw-bold">Cambiar contraseña</h6>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Nueva contraseña</label>
      <input name="nueva_clave" type="password" class="form-control" minlength="6" autocomplete="new-password">
    </div>
    <div class="col-md-6">
      <label class="form-label">Repetir</label>
      <input name="clave_rep" type="password" class="form-control" minlength="6" autocomplete="new-password">
    </div>
  </div>
  <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-circle"></i> Guardar</button>
</form>
</div></div>
</div></div>

<script>
// Previsualizar la foto carnet antes de subirla
(function(){
  var input = document.getElementById('fotoInput');
  if (!input) return;
  input.addEventListener('change', function(){
    var file = this.files && this.files[0];
    if (!file) return;
    if (file.size > 3*1024*1024) { alert('La foto supera los 3 MB.'); this.value = ''; return; }
    var url = URL.createObjectURL(file);
    var old = document.getElementById('previewFoto');
    var img = document.createElement('img');
    img.id = 'previewFoto';
    img.src = url;
    img.alt = 'Foto de perfil';
    img.setAttribute('style','width:140px;height:180px;object-fit:cover;border:3px solid #0d6efd;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);');
    old.parentNode.replaceChild(img, old);
  });
})();
</script>

<?php include 'includes/foot.php'; ?>
