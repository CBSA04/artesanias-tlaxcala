<?php $me = current_user(); $notices = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); ?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Descubre textiles, cerámica y objetos artesanales de Tlaxcala. Conoce sus materiales, su historia y a los talleres detrás de cada pieza.">
  <meta name="theme-color" content="#762d3e">
  <title><?= e($title ?? 'Artesanías de Tlaxcala') ?> · Artesanías de Tlaxcala</title>
  <link rel="icon" type="image/svg+xml" href="<?= url('/assets/favicon.svg') ?>">
  <link rel="stylesheet" href="<?= url('/assets/app.css') ?>">
  <script src="<?= url('/assets/app.js') ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="top-strip"><span>Manos que crean. Historias que permanecen.</span><span><?= icon('pin') ?> Tlaxcala, México</span></div>
<header class="site-header">
  <div class="nav-wrap">
    <a href="<?= url('/') ?>" class="brand" aria-label="Artesanías de Tlaxcala, inicio"><svg class="brand-mark" viewBox="0 0 48 48" aria-hidden="true"><path d="m24 3 9 12 12 9-12 9-9 12-9-12L3 24l12-9Z" fill="currentColor"/><path d="m24 12 12 12-12 12-12-12Z" fill="var(--paper)"/><path d="m24 19 5 5-5 5-5-5Z" fill="currentColor"/></svg><span>artesanías<span class="brand-place">DE TLAXCALA</span></span></a>
    <nav class="desktop-nav" aria-label="Principal"><a href="<?= url('/#coleccion') ?>">La colección</a><a href="<?= url('/acerca') ?>">Nuestro origen</a><?php if ($me && $me['role'] === 'vendedor'): ?><a href="<?= url('/vendedor') ?>">Mi taller</a><?php elseif ($me && $me['role'] === 'administrador'): ?><a href="<?= url('/admin') ?>">Administración</a><?php endif; ?></nav>
    <div class="nav-actions">
    <?php if ($me): ?>
      <a class="account-link" href="<?= url('/cuenta') ?>"><span class="avatar small"><?= e(initials($me['name'])) ?></span><span class="account-name"><?= e(explode(' ', $me['name'])[0]) ?></span></a>
      <?php if ($me['role'] === 'cliente'): ?><a class="bag-link" href="<?= url('/carrito') ?>" aria-label="Ver carrito, <?= cart_count() ?> artículos"><?= icon('bag') ?><span class="bag-count"><?= cart_count() ?></span></a><?php endif; ?>
      <form action="<?= url('/salir') ?>" method="post" class="logout-form"><?= csrf_field() ?><button class="icon-button" aria-label="Cerrar sesión" title="Cerrar sesión"><?= icon('logout') ?></button></form>
    <?php else: ?>
      <a class="login-link" href="<?= url('/ingresar') ?>"><?= icon('user') ?><span>Ingresar</span></a><a class="button button-small button-dark register-nav" href="<?= url('/registro') ?>">Crear cuenta</a>
    <?php endif; ?>
      <button class="icon-button menu-toggle" data-menu aria-controls="mobile-nav" aria-expanded="false" aria-label="Abrir menú"><?= icon('menu') ?></button>
    </div>
  </div>
  <nav id="mobile-nav" class="mobile-nav" hidden aria-label="Navegación móvil"><a href="<?= url('/#coleccion') ?>">La colección</a><a href="<?= url('/acerca') ?>">Nuestro origen</a><a href="<?= url($me ? '/cuenta' : '/registro') ?>"><?= $me ? 'Mi cuenta' : 'Crear cuenta' ?></a></nav>
</header>
<?php if ($notices): ?><div class="flash-wrap container" aria-live="polite"><?php foreach ($notices as $notice): ?><div class="notice <?= e($notice['type']) ?>" role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>"><?= icon($notice['type'] === 'success' ? 'check' : 'leaf') ?><span><?= e($notice['message']) ?></span><button class="icon-button" data-dismiss aria-label="Cerrar aviso"><?= icon('close') ?></button></div><?php endforeach; ?></div><?php endif; ?>
<main id="contenido"><?= $content ?></main>
<footer class="site-footer">
  <div class="footer-main container"><div><a class="brand footer-brand" href="<?= url('/') ?>"><span class="footer-symbol">✥</span><span>artesanías<span class="brand-place">DE TLAXCALA</span></span></a><p>Objetos con memoria.<br>Hechos para acompañarte.</p></div><div><h2>Descubre</h2><a href="<?= url('/?category=textiles#coleccion') ?>">Textiles</a><a href="<?= url('/?category=ceramica#coleccion') ?>">Cerámica</a><a href="<?= url('/?category=madera#coleccion') ?>">Madera</a></div><div><h2>Tu espacio</h2><a href="<?= url('/cuenta') ?>">Mi cuenta</a><a href="<?= url('/pedidos') ?>">Mis pedidos</a><a href="<?= url('/acerca#compras') ?>">Cómo comprar</a></div><div class="footer-origin"><span class="eyebrow">HECHO CERCA. HECHO CON ALMA.</span><p>Una ventana a la creatividad<br>de Tlaxcala y su región.</p><span class="origin-pin"><?= icon('pin') ?> Tlaxcala, México</span></div></div>
  <div class="footer-bottom container"><span>© <?= date('Y') ?> Artesanías de Tlaxcala</span><a href="<?= url('/acerca#privacidad') ?>">Privacidad y uso del sitio</a><span>Celebramos el trabajo de nuestras manos.</span></div>
</footer>
</body></html>
