</div><!-- /container -->
<footer class="text-center text-muted small py-4">
  <i class="bi bi-wallet2"></i> Control de Gastos · v<?= APP_VER ?>
</footer>
</main>

<?php if (!empty($_SESSION['user_id'])):
    // Cargar notificaciones no leidas del usuario
    try {
        $qn = $pdo->prepare("SELECT id, titulo, mensaje, tipo, enlace, creado_en
                             FROM notificaciones
                             WHERE usuario_id=? AND leida=0
                             ORDER BY creado_en DESC");
        $qn->execute([(int)$_SESSION['user_id']]);
        $notifPend = $qn->fetchAll();
    } catch (Exception $e) { $notifPend = []; }
?>
<?php if (!empty($notifPend)): ?>
<!-- ============================================================ -->
<!--  POP-UP DE NOTIFICACIONES PENDIENTES                          -->
<!-- ============================================================ -->
<div class="modal fade" id="mNotif" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
          <i class="bi bi-bell-fill me-2"></i>
          Tienes <?= count($notifPend) ?> notificacion<?= count($notifPend)===1?'':'es' ?> nueva<?= count($notifPend)===1?'':'s' ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-0">
        <div class="list-group list-group-flush" id="notifList">
          <?php foreach ($notifPend as $n):
            $tipo = $n['tipo'] ?: 'info';
            $iconMap = [
                'info'    => ['bi-info-circle-fill',     '#3b82f6'],
                'success' => ['bi-check-circle-fill',    '#10b981'],
                'warning' => ['bi-exclamation-triangle-fill', '#f59e0b'],
                'error'   => ['bi-x-octagon-fill',       '#ef4444'],
            ];
            $ico = $iconMap[$tipo] ?? $iconMap['info'];
          ?>
          <div class="list-group-item py-3 notif-item" data-id="<?= (int)$n['id'] ?>">
            <div class="d-flex gap-3">
              <div style="color:<?= $ico[1] ?>; font-size:1.6rem; line-height:1;">
                <i class="bi <?= $ico[0] ?>"></i>
              </div>
              <div class="flex-grow-1">
                <div class="fw-bold mb-1"><?= h($n['titulo']) ?></div>
                <?php if ($n['mensaje']): ?>
                  <div class="small text-muted mb-2"><?= nl2br(h($n['mensaje'])) ?></div>
                <?php endif; ?>
                <div class="small text-muted">
                  <i class="bi bi-clock"></i> <?= h(date('d/m/Y H:i', strtotime($n['creado_en']))) ?>
                </div>
                <?php if ($n['enlace']): ?>
                  <div class="mt-2">
                    <a href="<?= h($n['enlace']) ?>" class="btn btn-sm btn-primary notif-ir" data-id="<?= (int)$n['id'] ?>">
                      <i class="bi bi-arrow-right-circle me-1"></i>Ir a revisar
                    </a>
                  </div>
                <?php endif; ?>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary notif-marcar" data-id="<?= (int)$n['id'] ?>" title="Marcar como leida">
                <i class="bi bi-check2"></i>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <small class="text-muted">Las notificaciones se marcan como leidas al revisarlas.</small>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnMarcarTodas">
          <i class="bi bi-check-all me-1"></i>Marcar todas como leidas
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/app.js?v=<?= APP_VER ?>"></script>

<?php if (!empty($notifPend)): ?>
<script>
// Pop-up automatico de notificaciones nuevas
(function(){
  var modalEl = document.getElementById('mNotif');
  if (!modalEl) return;
  var modal = new bootstrap.Modal(modalEl);
  // Mostrar al cargar la pagina (solo la primera vez por sesion de pestana)
  var keyShown = 'notifShown_' + <?= (int)$_SESSION['user_id'] ?> + '_' + <?= count($notifPend) ?>;
  if (!sessionStorage.getItem(keyShown)) {
    setTimeout(function(){ modal.show(); }, 400);
    sessionStorage.setItem(keyShown, '1');
  }
  // Reabrir desde los botones de campana
  ['btnNotifTop','btnNotifSide'].forEach(function(id){
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', function(e){ e.preventDefault(); modal.show(); });
  });

  function marcarLeida(id){
    var fd = new FormData();
    fd.append('accion','marcar_leida');
    fd.append('id', id);
    return fetch('notificaciones_api.php', { method:'POST', body: fd }).catch(function(){});
  }

  // Boton individual "marcar como leida"
  document.querySelectorAll('.notif-marcar').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = this.dataset.id;
      var item = this.closest('.notif-item');
      marcarLeida(id).then(function(){
        if (item) item.remove();
        if (document.querySelectorAll('#notifList .notif-item').length === 0) {
          modal.hide();
          location.reload();
        }
      });
    });
  });

  // Click en "Ir a revisar": marcar leida y seguir navegando
  document.querySelectorAll('.notif-ir').forEach(function(a){
    a.addEventListener('click', function(e){
      e.preventDefault();
      var href = this.getAttribute('href');
      marcarLeida(this.dataset.id).finally(function(){
        window.location.href = href;
      });
    });
  });

  // Boton "Marcar todas como leidas"
  var btnTodas = document.getElementById('btnMarcarTodas');
  if (btnTodas) {
    btnTodas.addEventListener('click', function(){
      var fd = new FormData();
      fd.append('accion','marcar_todas');
      fetch('notificaciones_api.php', { method:'POST', body: fd })
        .finally(function(){ modal.hide(); location.reload(); });
    });
  }
})();
</script>
<?php endif; ?>

<script>
// === Limpieza de backdrops huerfanos (Bootstrap) ===
// Si por alguna razon un modal/offcanvas deja un backdrop activo,
// los clicks de la pagina se bloquean. Esto lo previene.
(function(){
  function limpiarBackdropsHuerfanos(){
    var modalAbierto = document.querySelector('.modal.show');
    var offcanvasAbierto = document.querySelector('.offcanvas.show');
    if (!modalAbierto) {
      document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
    }
    if (!offcanvasAbierto) {
      document.querySelectorAll('.offcanvas-backdrop').forEach(function(b){ b.remove(); });
    }
    if (!modalAbierto && !offcanvasAbierto) {
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
      document.body.style.paddingRight = '';
    }
  }
  // Al cargar la pagina
  limpiarBackdropsHuerfanos();
  // Tras cerrar cualquier modal u offcanvas
  document.addEventListener('hidden.bs.modal', function(){ setTimeout(limpiarBackdropsHuerfanos, 100); });
  document.addEventListener('hidden.bs.offcanvas', function(){ setTimeout(limpiarBackdropsHuerfanos, 100); });
})();

// === Sidebar movil: cierre y navegacion robustos ===
(function(){
  var sidebar = document.getElementById('sidebar');
  if (!sidebar) return;

  function isMobile(){ return window.innerWidth < 992; }
  function getOffcanvas(){
    if (!window.bootstrap) return null;
    return bootstrap.Offcanvas.getInstance(sidebar) || new bootstrap.Offcanvas(sidebar);
  }

  // 1) Cerrar offcanvas al hacer click en un link interno (movil).
  //    Navegacion diferida para que la animacion no aborte el click.
  sidebar.querySelectorAll('a[href]:not([href^="#"])').forEach(function(a){
    a.addEventListener('click', function(e){
      if (!isMobile()) return;
      var href = a.getAttribute('href');
      var target = a.getAttribute('target');
      // Permitir confirmar (ej. logout) sin interferir
      var inst = getOffcanvas();
      if (inst) inst.hide();
      if (target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey) return;
    });
  });

  // 2) Cerrar al hacer click fuera (sobre el backdrop) - Bootstrap ya lo hace,
  //    pero garantizamos que el body se desbloquee si la animacion falla.
  sidebar.addEventListener('hidden.bs.offcanvas', function(){
    document.body.classList.remove('modal-open','offcanvas-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    document.querySelectorAll('.offcanvas-backdrop').forEach(function(b){ b.remove(); });
  });

  // 3) Si por algun motivo el sidebar quedo "show" tras cambiar de tamano,
  //    forzar cierre al pasar a escritorio.
  window.addEventListener('resize', function(){
    if (!isMobile() && sidebar.classList.contains('show')){
      var inst = bootstrap.Offcanvas.getInstance(sidebar);
      if (inst) inst.hide();
    }
  });

  // 4) Boton hamburguesa: garantizar que SIEMPRE abra (por si el data-bs-toggle falla).
  var burger = document.querySelector('[data-bs-target="#sidebar"][data-bs-toggle="offcanvas"]');
  if (burger){
    burger.addEventListener('click', function(e){
      if (!isMobile()) return;
      e.preventDefault();
      var inst = getOffcanvas();
      if (sidebar.classList.contains('show')) inst.hide(); else inst.show();
    });
  }
})();

// === Service worker ===
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(function(){});
}
</script>
</body>
</html>
