<?php
if (!isset($_SESSION)) session_start();
$rol = $_SESSION['user_rol'] ?? '';
$nombre = $_SESSION['user_nombre'] ?? 'Invitado';
$nick = $_SESSION['user_usuario'] ?? '';
$current = basename($_SERVER['PHP_SELF']);

// Foto del usuario actual (para el avatar del sidebar)
$fotoActualUser = '';
$notifCount = 0;
if (!empty($_SESSION['user_id']) && isset($pdo)) {
    try {
        $qf = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id=?");
        $qf->execute([(int)$_SESSION['user_id']]);
        $fotoActualUser = $qf->fetchColumn() ?: '';
        $qn = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id=? AND leida=0");
        $qn->execute([(int)$_SESSION['user_id']]);
        $notifCount = (int)$qn->fetchColumn();
    } catch (Exception $e) { $fotoActualUser = ''; }
}
$urlFotoUser = function_exists('urlFotoUsuario') ? urlFotoUsuario($fotoActualUser) : '';

function sidelink($href, $label, $icon, $current) {
    $active = ($current === $href) ? 'active' : '';
    return "<li><a class='side-link $active' href='$href'>
              <span class='side-icon'><i class='bi $icon'></i></span>
              <span class='side-label'>$label</span>
            </a></li>";
}

$links = [];
if ($rol === 'usuario') {
    $links[] = ['dashboard.php',   'Inicio',           'bi-house-door-fill'];
    $links[] = ['gasto_nuevo.php', 'Registrar gasto',  'bi-plus-circle-fill'];
    $links[] = ['mis_gastos.php',  'Mis gastos',       'bi-list-ul'];
    $links[] = ['gastos_faena.php','Gastos de Faena',  'bi-receipt-cutoff'];
    $links[] = ['cierre_mes.php',  'Cierre mensual',   'bi-send-check-fill'];
} elseif ($rol === 'jefe') {
    $links[] = ['jefe_dashboard.php', 'Mi equipo',          'bi-people-fill'];
    $links[] = ['jefe_cierres.php',   'Cierres del equipo', 'bi-clipboard-check-fill'];
    $links[] = ['gastos_faena.php',   'Gastos de Faena',    'bi-receipt-cutoff'];
} elseif ($rol === 'validador') {
    $links[] = ['validador_dashboard.php', 'Cierres',            'bi-shield-check'];
    $links[] = ['validador_gastos.php',    'Gastos por tecnico', 'bi-people-fill'];
    $links[] = ['gastos_faena.php',        'Gastos de Faena',    'bi-receipt-cutoff'];
} elseif ($rol === 'admin') {
    $links[] = ['admin_usuarios.php',     'Usuarios',           'bi-people-fill'];
    $links[] = ['admin_asignaciones.php', 'Asignaciones',       'bi-cash-coin'];
    $links[] = ['admin_gastos.php',       'Gastos por tecnico', 'bi-person-vcard'];
    $links[] = ['admin_categorias.php',   'Categorias',         'bi-tags-fill'];
    $links[] = ['gastos_faena.php',       'Gastos de Faena',    'bi-receipt-cutoff'];
    $links[] = ['admin_obras_faena.php',  'Obras (Faena)',      'bi-building'];
}
?>

<!-- Topbar móvil -->
<header class="mobile-topbar d-lg-none">
  <button type="button" class="btn-burger" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menú">
    <i class="bi bi-list"></i>
  </button>
  <div class="mobile-brand">
    <i class="bi bi-wallet2"></i> Control de Gastos
  </div>
  <button type="button" class="btn-burger position-relative" id="btnNotifTop" aria-label="Notificaciones">
    <i class="bi bi-bell-fill"></i>
    <?php if ($notifCount > 0): ?>
      <span class="position-absolute translate-middle badge rounded-pill bg-danger" style="top:8px;right:4px;font-size:10px;">
        <?= $notifCount > 9 ? '9+' : $notifCount ?>
      </span>
    <?php endif; ?>
  </button>
  <a href="perfil.php" class="btn-burger" aria-label="Perfil">
    <i class="bi bi-person-circle"></i>
  </a>
  <a href="logout.php" class="btn-burger" aria-label="Cerrar sesion"
     onclick="return confirm('¿Cerrar sesion?');" title="Cerrar sesion">
    <i class="bi bi-box-arrow-right"></i>
  </a>
</header>

<!-- Sidebar -->
<aside class="sidebar offcanvas-lg offcanvas-start" id="sidebar" tabindex="-1">
  <div class="sidebar-brand">
    <div class="sidebar-logo"><i class="bi bi-wallet2"></i></div>
    <div>
      <div class="sidebar-title">Control de Gastos</div>
      <div class="sidebar-subtitle">v<?= APP_VER ?></div>
    </div>
    <button class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
  </div>

  <nav class="sidebar-nav">
    <div class="side-section">Menú</div>
    <ul class="side-list">
      <?php foreach ($links as $l) echo sidelink($l[0], $l[1], $l[2], $current); ?>
    </ul>

    <div class="side-section">Cuenta</div>
    <ul class="side-list">
      <li>
        <a class="side-link position-relative" href="#" id="btnNotifSide">
          <span class="side-icon"><i class="bi bi-bell-fill"></i></span>
          <span class="side-label">Notificaciones
            <?php if ($notifCount > 0): ?>
              <span class="badge bg-danger ms-1"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
            <?php endif; ?>
          </span>
        </a>
      </li>
      <?= sidelink('perfil.php', 'Mi perfil', 'bi-person-fill-gear', $current) ?>
      <li><a class="side-link side-link-danger" href="logout.php" id="btnLogout"
             onclick="return confirm('¿Cerrar sesion?');">
        <span class="side-icon"><i class="bi bi-box-arrow-right"></i></span>
        <span class="side-label">Cerrar sesion</span>
      </a></li>
    </ul>
  </nav>

  <div class="sidebar-user">
    <?php if ($urlFotoUser): ?>
      <img class="user-avatar" src="<?= h($urlFotoUser) ?>" alt="<?= h($nombre) ?>"
           style="object-fit:cover;padding:0;">
    <?php else: ?>
      <div class="user-avatar"><?= strtoupper(substr($nick ?: $nombre ?: '?', 0, 1)) ?></div>
    <?php endif; ?>
    <div class="user-info">
      <div class="user-name"><?= h($nick ?: $nombre) ?></div>
      <div class="user-rol"><?= h(ucfirst($rol)) ?></div>
    </div>
    <a href="perfil.php" class="user-edit" title="Editar perfil"><i class="bi bi-gear"></i></a>
  </div>
</aside>

<!-- Contenido principal -->
<main class="main-content">
<div class="container-fluid p-3 p-md-4">
<?php
foreach (getFlash() as $f):
    $m = ['exito'=>'success','error'=>'danger','warn'=>'warning','info'=>'info'][$f['tipo']] ?? 'info';
?>
<div class="alert alert-<?= $m ?> alert-dismissible fade show" role="alert">
  <?= h($f['msg']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>
