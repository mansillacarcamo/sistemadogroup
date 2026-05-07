<?php
require_once 'config.php';
requireRol('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Blindaje: si algo explota, mostrar error claro en lugar de pantalla blanca
    try {
    $acc = $_POST['accion'] ?? '';

    if ($acc === 'crear') {
        $nombre  = trim($_POST['nombre'] ?? '');
        $usuario = strtolower(trim($_POST['usuario'] ?? ''));
        $email   = trim($_POST['email'] ?? '');
        $clave   = $_POST['clave'] ?? '123456';
        $rol     = $_POST['rol'] ?? 'usuario';
        $jefe    = $_POST['jefe_zonal_id'] ?: null;
        $val     = $_POST['validador_id'] ?: null;
        $cargo   = trim($_POST['cargo'] ?? '');
        $zona    = trim($_POST['zona'] ?? '');
        $ciudad  = trim($_POST['ciudad'] ?? '');
        $region  = trim($_POST['region'] ?? '');
        $rut     = trim($_POST['rut'] ?? '');
        $tel     = trim($_POST['telefono'] ?? '');

        if (!$nombre || !$usuario || !$email) {
            flash('error','Nombre, usuario y email son obligatorios.');
        } elseif (!preg_match('/^[a-z0-9._]{3,30}$/', $usuario)) {
            flash('error','El usuario solo puede tener minúsculas, números, punto (.) o guión bajo (_). Entre 3 y 30 caracteres.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error','Email inválido.');
        } else {
            // Verificar duplicados explicitamente para dar mensaje claro
            $chk = $pdo->prepare("SELECT usuario, email FROM usuarios WHERE usuario=? OR email=? LIMIT 1");
            $chk->execute([$usuario, $email]);
            $dup = $chk->fetch();
            if ($dup) {
                $motivo = ($dup['usuario'] === $usuario) ? 'El usuario "'.$usuario.'" ya existe' : 'El email "'.$email.'" ya existe';
                flash('error', 'No se pudo crear: '.$motivo.'.');
            } else {
                try {
                    $pdo->prepare("INSERT INTO usuarios (nombre,usuario,email,clave,rol,rut,cargo,zona,ciudad,region,telefono,jefe_zonal_id,validador_id)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$nombre,$usuario,$email,password_hash($clave,PASSWORD_DEFAULT),$rol,$rut,$cargo,$zona,$ciudad,$region,$tel,$jefe,$val]);
                    $nuevoId = (int)$pdo->lastInsertId();
                    if ($nuevoId <= 0) {
                        flash('error','El INSERT no devolvio un ID. Revisa permisos de escritura sobre controlgastos.db.');
                    } else {
                        // Foto carnet (opcional al crear)
                        try {
                            $nuevaFoto = procesarFotoPerfil('foto_perfil', $nuevoId, null);
                            if ($nuevaFoto) {
                                $pdo->prepare("UPDATE usuarios SET foto_perfil=? WHERE id=?")->execute([$nuevaFoto, $nuevoId]);
                            }
                        } catch (Exception $ef) {
                            flash('error','Usuario creado (ID '.$nuevoId.'), pero la foto no se subio: '.$ef->getMessage());
                        }
                        flash('exito','Usuario "'.$usuario.'" creado correctamente (ID '.$nuevoId.').');
                    }
                } catch (Exception $e) {
                    flash('error','Error al crear usuario: '.$e->getMessage());
                }
            }
        }

    } elseif ($acc === 'asignar') {
        $id    = (int)$_POST['id'];
        $anio  = (int)$_POST['anio'];
        $mes   = (int)$_POST['mes'];
        $monto = parseMonto($_POST['monto'] ?? 0);
        $carry = parseMonto($_POST['carry'] ?? 0);
        $obs   = trim($_POST['obs'] ?? '');
        if ($mes < 1 || $mes > 12 || $anio < 2020) {
            flash('error','Periodo inválido.');
        } else {
            $q = $pdo->prepare("SELECT id FROM asignaciones WHERE usuario_id=? AND anio=? AND mes=?");
            $q->execute([$id,$anio,$mes]);
            if ($aid = $q->fetchColumn()) {
                $pdo->prepare("UPDATE asignaciones SET monto_asignado=?, monto_carryover=?, observaciones=?, asignado_por=? WHERE id=?")
                    ->execute([$monto,$carry,$obs,$_SESSION['user_id'],$aid]);
            } else {
                $pdo->prepare("INSERT INTO asignaciones (usuario_id,anio,mes,monto_asignado,monto_carryover,observaciones,asignado_por) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$id,$anio,$mes,$monto,$carry,$obs,$_SESSION['user_id']]);
            }
            flash('exito','Monto asignado a '.nombreMes($mes).' '.$anio.'.');
        }

    } elseif ($acc === 'editar') {
        $id      = (int)$_POST['id'];
        $usuario = strtolower(trim($_POST['usuario'] ?? ''));
        if (!preg_match('/^[a-z0-9._]{3,30}$/', $usuario)) {
            flash('error','Usuario inválido al editar.');
        } else {
            try {
                $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, email=?, rol=?, rut=?, cargo=?, zona=?, ciudad=?, region=?, telefono=?, jefe_zonal_id=?, validador_id=?, activo=? WHERE id=?")
                    ->execute([
                        trim($_POST['nombre'] ?? ''), $usuario, trim($_POST['email'] ?? ''),
                        $_POST['rol'] ?? 'usuario',
                        trim($_POST['rut'] ?? ''), trim($_POST['cargo'] ?? ''),
                        trim($_POST['zona'] ?? ''), trim($_POST['ciudad'] ?? ''), trim($_POST['region'] ?? ''),
                        trim($_POST['telefono'] ?? ''),
                        $_POST['jefe_zonal_id'] ?: null, $_POST['validador_id'] ?: null,
                        isset($_POST['activo']) ? 1 : 0, $id
                    ]);
                if (!empty($_POST['nueva_clave'])) {
                    $pdo->prepare("UPDATE usuarios SET clave=? WHERE id=?")
                        ->execute([password_hash($_POST['nueva_clave'],PASSWORD_DEFAULT),$id]);
                }
                // Foto carnet (opcional al editar)
                try {
                    $fa = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id=?");
                    $fa->execute([$id]);
                    $fotoActual = $fa->fetchColumn();
                    $nuevaFoto = procesarFotoPerfil('foto_perfil', $id, $fotoActual);
                    if ($nuevaFoto) {
                        $pdo->prepare("UPDATE usuarios SET foto_perfil=? WHERE id=?")->execute([$nuevaFoto, $id]);
                    }
                } catch (Exception $ef) {
                    flash('error','Datos actualizados, pero la foto no se subio: '.$ef->getMessage());
                }
                flash('exito','Usuario actualizado.');
            } catch (Exception $e) {
                flash('error','Error al actualizar: '.$e->getMessage());
            }
        }
    }
    } catch (Throwable $errGlobal) {
        flash('error', 'Error inesperado al guardar: ' . $errGlobal->getMessage()
            . ' (en ' . basename($errGlobal->getFile()) . ':' . $errGlobal->getLine() . ')');
    }

    if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_clean(); }
    header('Location: admin_usuarios.php');
    exit;
}

$jefes = $pdo->query("SELECT id,nombre FROM usuarios WHERE rol='jefe' AND activo=1 ORDER BY nombre")->fetchAll();
$vals  = $pdo->query("SELECT id,nombre FROM usuarios WHERE rol='validador' AND activo=1 ORDER BY nombre")->fetchAll();
$p = periodoActual();
$anioDef = $p['anio']; $mesDef = $p['mes'];

$users = $pdo->query("SELECT u.*,
    (SELECT nombre FROM usuarios j WHERE j.id=u.jefe_zonal_id) as jefe_nombre,
    (SELECT nombre FROM usuarios v WHERE v.id=u.validador_id) as val_nombre
    FROM usuarios u ORDER BY u.rol, u.nombre")->fetchAll();

$asigs = [];
$qa = $pdo->prepare("SELECT * FROM asignaciones WHERE anio=? AND mes=?");
$qa->execute([$anioDef, $mesDef]);
foreach ($qa->fetchAll() as $a) $asigs[$a['usuario_id']] = $a;

$titulo = 'Usuarios';
include 'includes/head.php';
include 'includes/nav.php';
?>
<a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver</a>


<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-people me-2"></i>Usuarios (<?= count($users) ?>)</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mNuevo">
    <i class="bi bi-plus-lg"></i> Nuevo usuario
  </button>
</div>

<div class="card"><div class="card-body p-0">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Region / Ciudad</th>
          <th>Jefe Z.</th><th>Validador</th>
          <th>Asignado <?= nombreMes($mesDef) ?></th>
          <th>Estado</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?= avatarUsuario($u['nombre'] ?: $u['usuario'], $u['foto_perfil'] ?? '', 40) ?>
              <div>
                <div><?= h($u['nombre']) ?></div>
                <?php if ($u['cargo']): ?><div class="small text-muted"><?= h($u['cargo']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><span class="badge bg-light text-dark border"><?= h($u['usuario']) ?></span></td>
          <td class="small"><?= h($u['email']) ?></td>
          <td><span class="badge bg-secondary"><?= h($u['rol']) ?></span></td>
          <td class="small">
            <div><?= h($u['region'] ?: '-') ?></div>
            <?php if ($u['ciudad']): ?><div class="text-muted"><i class="bi bi-geo-alt"></i> <?= h($u['ciudad']) ?></div><?php endif; ?>
            <?php if ($u['zona']): ?><div class="text-muted small">Zona: <?= h($u['zona']) ?></div><?php endif; ?>
          </td>
          <td class="small"><?= h($u['jefe_nombre'] ?: '—') ?></td>
          <td class="small"><?= h($u['val_nombre'] ?: '—') ?></td>
          <td>
            <?php if ($u['rol'] === 'usuario'):
              $a = $asigs[$u['id']] ?? null;
              $total = $a ? ((float)$a['monto_asignado'] + (float)$a['monto_carryover']) : 0;
            ?>
              <span class="fw-semibold text-<?= $total>0?'success':'muted' ?>"><?= $a ? fmtCLP($total) : '—' ?></span>
            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
          </td>
          <td><?= $u['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
          <td class="text-nowrap">
            <?php if ($u['rol'] === 'usuario'): ?>
              <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#mA<?= $u['id'] ?>" title="Asignar monto">
                <i class="bi bi-cash-coin"></i>
              </button>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mE<?= $u['id'] ?>" title="Editar">
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<!-- ====================================================== -->
<!-- MODAL NUEVO USUARIO                                     -->
<!-- ====================================================== -->
<div class="modal fade" id="mNuevo" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" id="formNuevo" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="crear">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-person-plus me-1"></i>Nuevo usuario</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Foto carnet -->
          <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div id="prevFotoNuevo" class="d-flex align-items-center justify-content-center bg-light text-muted"
                 style="width:90px;height:115px;border:2px dashed #cbd5e1;border-radius:6px;flex-shrink:0;">
              <i class="bi bi-person-bounding-box" style="font-size:2rem;"></i>
            </div>
            <div class="flex-grow-1">
              <label class="form-label fw-semibold mb-1">Foto carnet del técnico</label>
              <input type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm foto-preview-input" data-preview="prevFotoNuevo">
              <div class="form-text small">Opcional · JPG/PNG/WEBP · máx 3 MB</div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nombre completo *</label>
              <input name="nombre" class="form-control" required autofocus>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Usuario *</label>
              <input name="usuario" class="form-control input-user" placeholder="jperez" required
                     autocapitalize="none" autocorrect="off" spellcheck="false"
                     title="Minúsculas, números, . o _ (3-30)">
              <div class="form-text small">minúsculas, sin espacios</div>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Email *</label>
              <input name="email" type="email" class="form-control" required>
            </div>
            <div class="col-md-12">
              <div class="card border-primary bg-primary bg-opacity-10 mb-2">
                <div class="card-body py-2 px-3">
                  <label class="form-label fw-bold mb-1 text-primary"><i class="bi bi-person-badge"></i> Perfil del usuario *</label>
                  <select name="rol" id="rolNuevo" class="form-select form-select-lg fw-semibold">
                    <option value="usuario">Tecnico (registra sus gastos)</option>
                    <option value="jefe">Jefe Zonal (revisa y aprueba gastos de tecnicos)</option>
                    <option value="validador">Validador (valida cierres mensuales)</option>
                    <option value="admin">Administrador (gestion completa del sistema)</option>
                  </select>
                  <div class="form-text small mt-1" id="rolNuevoHelp">
                    <i class="bi bi-info-circle"></i> Selecciona el rol que tendra este usuario al ingresar al sistema.
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">RUT</label>
              <input name="rut" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input name="telefono" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Cargo</label>
              <input name="cargo" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Region</label>
              <select name="region" class="form-select">
                <option value="">- Seleccionar -</option>
                <?php foreach (regionesChile() as $r): ?>
                  <option value="<?= h($r) ?>"><?= h($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Ciudad</label>
              <input name="ciudad" class="form-control" placeholder="Ej. Talca, Concepcion, Temuco...">
            </div>
            <div class="col-md-6">
              <label class="form-label">Zona / Sector</label>
              <input name="zona" class="form-control" placeholder="Ej. Norte, Sur, Centro...">
            </div>
            <div class="col-md-6 jefe-tecnico-only">
              <label class="form-label">Jefe zonal asignado</label>
              <select name="jefe_zonal_id" class="form-select">
                <option value="">— Ninguno —</option>
                <?php foreach ($jefes as $j): ?>
                  <option value="<?= $j['id'] ?>"><?= h($j['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small">Solo para tecnicos: jefe que revisara sus gastos.</div>
            </div>
            <div class="col-md-6 jefe-tecnico-only">
              <label class="form-label">Validador asignado</label>
              <select name="validador_id" class="form-select">
                <option value="">— Ninguno —</option>
                <?php foreach ($vals as $v): ?>
                  <option value="<?= $v['id'] ?>"><?= h($v['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small">Solo para tecnicos: valida cierres mensuales.</div>
            </div>
            <div class="col-md-12">
              <label class="form-label">Contraseña inicial</label>
              <input name="clave" type="text" class="form-control" value="123456">
              <div class="form-text small">El usuario podrá cambiarla luego desde su perfil.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Crear usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ====================================================== -->
<!-- MODALES DE CADA USUARIO (editar + asignar)              -->
<!-- ====================================================== -->
<?php foreach ($users as $u): ?>

  <!-- Editar usuario #<?= $u['id'] ?> -->
  <div class="modal fade" id="mE<?= $u['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="accion" value="editar">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>Editar · <?= h($u['nombre'] ?: $u['usuario']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Foto carnet -->
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
              <?php $urlFotoU = urlFotoUsuario($u['foto_perfil'] ?? ''); ?>
              <?php if ($urlFotoU): ?>
                <img id="prevFotoE<?= $u['id'] ?>" src="<?= h($urlFotoU) ?>" alt="Foto"
                     style="width:90px;height:115px;object-fit:cover;border:2px solid #e5e7eb;border-radius:6px;flex-shrink:0;">
              <?php else: ?>
                <div id="prevFotoE<?= $u['id'] ?>" class="d-flex align-items-center justify-content-center bg-light text-muted"
                     style="width:90px;height:115px;border:2px dashed #cbd5e1;border-radius:6px;flex-shrink:0;">
                  <i class="bi bi-person-bounding-box" style="font-size:2rem;"></i>
                </div>
              <?php endif; ?>
              <div class="flex-grow-1">
                <label class="form-label fw-semibold mb-1">Foto carnet <span class="text-muted small">(opcional, reemplaza la actual)</span></label>
                <input type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm foto-preview-input" data-preview="prevFotoE<?= $u['id'] ?>">
                <div class="form-text small">JPG/PNG/WEBP · máx 3 MB</div>
              </div>
            </div>
            <div class="row g-2">
              <div class="col-md-6"><label class="form-label">Nombre</label>
                <input name="nombre" class="form-control" value="<?= h($u['nombre']) ?>" required></div>
              <div class="col-md-3"><label class="form-label">Usuario</label>
                <input name="usuario" class="form-control input-user" value="<?= h($u['usuario']) ?>" required
                       autocapitalize="none" autocorrect="off" spellcheck="false"></div>
              <div class="col-md-3"><label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" value="<?= h($u['email']) ?>" required></div>
              <div class="col-md-4"><label class="form-label">Rol</label>
                <select name="rol" class="form-select">
                  <?php foreach (['usuario','jefe','validador','admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['rol']===$r?'selected':'' ?>><?= $r ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4"><label class="form-label">RUT</label>
                <input name="rut" class="form-control" value="<?= h($u['rut']) ?>"></div>
              <div class="col-md-4"><label class="form-label">Teléfono</label>
                <input name="telefono" class="form-control" value="<?= h($u['telefono']) ?>"></div>
              <div class="col-md-6"><label class="form-label">Cargo</label>
                <input name="cargo" class="form-control" value="<?= h($u['cargo']) ?>"></div>
              <div class="col-md-6"><label class="form-label">Region</label>
                <select name="region" class="form-select">
                  <option value="">- Seleccionar -</option>
                  <?php foreach (regionesChile() as $r): ?>
                    <option value="<?= h($r) ?>" <?= ($u['region']??'')===$r?'selected':'' ?>><?= h($r) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6"><label class="form-label">Ciudad</label>
                <input name="ciudad" class="form-control" value="<?= h($u['ciudad']) ?>" placeholder="Ej. Talca, Concepcion..."></div>
              <div class="col-md-6"><label class="form-label">Zona / Sector</label>
                <input name="zona" class="form-control" value="<?= h($u['zona']) ?>" placeholder="Norte, Sur, Centro..."></div>
              <div class="col-md-6"><label class="form-label">Jefe zonal</label>
                <select name="jefe_zonal_id" class="form-select">
                  <option value="">— Ninguno —</option>
                  <?php foreach ($jefes as $j): ?>
                    <option value="<?= $j['id'] ?>" <?= $u['jefe_zonal_id']==$j['id']?'selected':'' ?>><?= h($j['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6"><label class="form-label">Validador</label>
                <select name="validador_id" class="form-select">
                  <option value="">— Ninguno —</option>
                  <?php foreach ($vals as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $u['validador_id']==$v['id']?'selected':'' ?>><?= h($v['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-8"><label class="form-label">Nueva contraseña <span class="text-muted small">(vacío = no cambia)</span></label>
                <input name="nueva_clave" type="text" class="form-control"></div>
              <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="activo" id="ac<?= $u['id'] ?>" <?= $u['activo']?'checked':'' ?>>
                  <label class="form-check-label" for="ac<?= $u['id'] ?>">Usuario activo</label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($u['rol'] === 'usuario'):
    $a = $asigs[$u['id']] ?? null;
  ?>
  <!-- Asignar monto a #<?= $u['id'] ?> -->
  <div class="modal fade" id="mA<?= $u['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="post">
          <input type="hidden" name="accion" value="asignar">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><i class="bi bi-cash-coin me-1"></i>Asignar monto · <?= h($u['nombre']) ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-light small mb-3">
              <strong><?= h($u['usuario']) ?></strong> · <?= h($u['email']) ?>
              <?php if ($u['zona']): ?> · Zona: <?= h($u['zona']) ?><?php endif; ?>
            </div>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small">Mes</label>
                <select name="mes" class="form-select">
                  <?php for($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $m===$mesDef?'selected':'' ?>><?= nombreMes($m) ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small">Año</label>
                <select name="anio" class="form-select">
                  <?php for($y=date('Y')+1;$y>=date('Y')-1;$y--): ?>
                    <option value="<?= $y ?>" <?= $y===$anioDef?'selected':'' ?>><?= $y ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Monto a asignar *</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text">$</span>
                  <input type="text" name="monto" class="form-control input-clp" inputmode="numeric"
                         value="<?= $a ? number_format($a['monto_asignado'],0,',','.') : '' ?>"
                         placeholder="ej. 500.000" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small">Arrastre mes anterior (opcional)</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="text" name="carry" class="form-control input-clp" inputmode="numeric"
                         value="<?= $a ? number_format($a['monto_carryover'],0,',','.') : '0' ?>">
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small">Observación</label>
                <input type="text" name="obs" class="form-control" value="<?= h($a['observaciones'] ?? '') ?>" placeholder="opcional">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Guardar asignación</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php endforeach; ?>

<script>
// Normalizar campo "usuario" a minúsculas y sin caracteres inválidos en tiempo real
document.querySelectorAll('.input-user').forEach(el => {
  el.addEventListener('input', () => {
    const old = el.selectionStart;
    el.value = el.value.toLowerCase().replace(/[^a-z0-9._]/g, '');
    el.setSelectionRange(old, old);
  });
});

// Mostrar pista contextual segun el rol seleccionado al crear usuario
(function(){
  var sel = document.getElementById('rolNuevo');
  var help = document.getElementById('rolNuevoHelp');
  if (!sel || !help) return;
  var msgs = {
    usuario:   '<i class="bi bi-tools"></i> <b>Tecnico:</b> registra sus propios gastos y los envia al Jefe Zonal para revision.',
    jefe:      '<i class="bi bi-shield-check"></i> <b>Jefe Zonal:</b> revisa, aprueba, observa o rechaza los gastos de sus tecnicos asignados.',
    validador: '<i class="bi bi-patch-check"></i> <b>Validador:</b> valida los cierres mensuales aprobados por los Jefes Zonales.',
    admin:     '<i class="bi bi-gear-fill"></i> <b>Administrador:</b> gestiona usuarios, asignaciones, categorias y todos los datos del sistema.'
  };
  function actualizar(){
    help.innerHTML = msgs[sel.value] || msgs.usuario;
    var soloTec = sel.value === 'usuario';
    document.querySelectorAll('.jefe-tecnico-only').forEach(function(el){
      el.style.display = soloTec ? '' : 'none';
    });
  }
  sel.addEventListener('change', actualizar);
  actualizar();
})();

// Previsualizar foto carnet en los modales de crear/editar usuario
document.querySelectorAll('.foto-preview-input').forEach(inp => {
  inp.addEventListener('change', function(){
    const file = this.files && this.files[0];
    if (!file) return;
    if (file.size > 3*1024*1024) { alert('La foto supera los 3 MB.'); this.value = ''; return; }
    const targetId = this.dataset.preview;
    const old = document.getElementById(targetId);
    if (!old) return;
    const url = URL.createObjectURL(file);
    const img = document.createElement('img');
    img.id = targetId;
    img.src = url;
    img.alt = 'Foto';
    img.setAttribute('style','width:90px;height:115px;object-fit:cover;border:2px solid #0d6efd;border-radius:6px;flex-shrink:0;');
    old.parentNode.replaceChild(img, old);
  });
});
</script>

<?php include 'includes/foot.php'; ?>
