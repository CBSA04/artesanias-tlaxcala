<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database as DB;
use App\Controllers\CatalogController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\SellerController;
use App\Controllers\AdminController;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');
if (env('APP_ENV') === 'production') header('Strict-Transport-Security: max-age=31536000');
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); abort_page(405, 'Método no permitido.'); }
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$prefix = base_path();
if ($prefix !== '' && str_starts_with($path, $prefix . '/')) $path = substr($path, strlen($prefix));
$path = rtrim($path, '/') ?: '/';
try {
    $GLOBALS['current_user'] = isset($_SESSION['user_id']) ? DB::one('SELECT id, name, email, role, active FROM users WHERE id=? AND active=1', [$_SESSION['user_id']]) : null;
    if (isset($_SESSION['user_id']) && !current_user()) { unset($_SESSION['user_id'], $_SESSION['cart'], $_SESSION['checkout_keys']); session_regenerate_id(true); }
    if ($method === 'POST' && (!is_string($_POST['_token'] ?? null) || !hash_equals(csrf(), $_POST['_token']))) abort_page(419, 'La sesión del formulario caducó. Actualiza la página e inténtalo de nuevo.');
    $routes = [
        ['GET', '#^/$#', CatalogController::class, 'index'],
        ['GET', '#^/producto/(\d+)$#', CatalogController::class, 'show'],
        ['GET', '#^/acerca$#', CatalogController::class, 'about'],
        ['GET', '#^/ingresar$#', AuthController::class, 'loginForm'],
        ['POST', '#^/ingresar$#', AuthController::class, 'login'],
        ['GET', '#^/registro$#', AuthController::class, 'registerForm'],
        ['POST', '#^/registro$#', AuthController::class, 'register'],
        ['POST', '#^/salir$#', AuthController::class, 'logout'],
        ['GET', '#^/cuenta$#', AuthController::class, 'account'],
        ['GET', '#^/carrito$#', CartController::class, 'index'],
        ['POST', '#^/carrito/agregar$#', CartController::class, 'add'],
        ['POST', '#^/carrito/actualizar$#', CartController::class, 'update'],
        ['POST', '#^/carrito/quitar$#', CartController::class, 'remove'],
        ['POST', '#^/pedidos/confirmar$#', CartController::class, 'checkout'],
        ['GET', '#^/pedidos$#', CartController::class, 'orders'],
        ['GET', '#^/pedidos/(\d+)$#', CartController::class, 'order'],
        ['GET', '#^/vendedor$#', SellerController::class, 'index'],
        ['GET', '#^/vendedor/productos/nuevo$#', SellerController::class, 'form'],
        ['GET', '#^/vendedor/productos/(\d+)/editar$#', SellerController::class, 'form'],
        ['POST', '#^/vendedor/productos$#', SellerController::class, 'save'],
        ['POST', '#^/vendedor/productos/(\d+)$#', SellerController::class, 'save'],
        ['GET', '#^/admin$#', AdminController::class, 'index'],
        ['GET', '#^/admin/usuarios/nuevo$#', AdminController::class, 'form'],
        ['GET', '#^/admin/usuarios/(\d+)/editar$#', AdminController::class, 'form'],
        ['POST', '#^/admin/usuarios$#', AdminController::class, 'save'],
        ['POST', '#^/admin/usuarios/(\d+)$#', AdminController::class, 'save'],
        ['GET', '#^/admin/usuarios/(\d+)/eliminar$#', AdminController::class, 'deleteForm'],
        ['POST', '#^/admin/usuarios/(\d+)/eliminar$#', AdminController::class, 'delete'],
    ];
    if ($method === 'GET' && preg_match('#^/media/([a-f0-9]{32}\.webp)$#D', $path, $matches)) {
        $file = ROOT . '/storage/uploads/' . $matches[1];
        if (!is_file($file)) abort_page(404, 'Esta imagen ya no está disponible.');
        header('Content-Type: image/webp'); header('Cache-Control: public, max-age=86400'); readfile($file); exit;
    }
    foreach ($routes as [$verb, $pattern, $controller, $action]) {
        if ($method === $verb && preg_match($pattern, $path, $matches)) { (new $controller)->$action(...array_map('intval', array_slice($matches, 1))); exit; }
    }
    abort_page(404, 'No encontramos esta página. Puedes volver a descubrir el catálogo.');
} catch (Throwable $e) {
    error_log(get_class($e) . ': ' . $e->getMessage());
    $dbIssue = $e instanceof PDOException;
    view('error', ['code' => 503, 'title' => 'Un momento', 'message' => $dbIssue ? 'No pudimos conectar con el catálogo. Si estás instalando la aplicación, revisa la configuración y ejecuta el instalador indicado en el README.' : 'No pudimos completar la solicitud. Inténtalo de nuevo en unos minutos.'], 503);
}
