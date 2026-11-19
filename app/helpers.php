<?php
declare(strict_types=1);

function env(string $key, string $fallback = ''): string { $v = getenv($key); return $v === false ? $fallback : $v; }
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_path(): string { return rtrim(env('APP_BASE_PATH', (string) parse_url(env('APP_URL', 'http://localhost:8080'), PHP_URL_PATH)), '/'); }
function url(string $path = '/'): string { return base_path() . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function money(int $cents): string { return '$' . number_format($cents / 100, 2, '.', ','); }
function cents(string $value): int {
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) throw new DomainException('Escribe un precio válido, con hasta dos decimales.');
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
}
function decimal(int $value): string { return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . csrf() . '">'; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'][] = ['message' => $message, 'type' => $type]; }
function current_user(): ?array { return $GLOBALS['current_user'] ?? null; }
function initials(string $name): string {
    $parts = preg_split('/\s+/u', trim($name));
    return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}
function role_label(string $role): string { return ['cliente' => 'Comprador', 'vendedor' => 'Vendedor', 'administrador' => 'Administrador'][$role] ?? $role; }
function image_url(string $path): string { return url($path); }
function cart_count(): int { return array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); }
function date_label(string $value): string { return date('d/m/Y · H:i', strtotime($value)); }
function icon(string $name, string $class = ''): string {
    $paths = [
        'bag' => '<path d="M6 7h12l1 14H5L6 7Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'check' => '<path d="m5 12 4 4 10-10"/>',
        'pin' => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>',
        'leaf' => '<path d="M20 4C6 2 2 9 6 16s16 0 14-12ZM5 21 16 9"/>',
        'heart' => '<path d="M20.8 4.6c-2.4-2.4-6.1-1.7-8.8 1.3C9.3 2.9 5.6 2.2 3.2 4.6c-3.5 3.5.5 9.6 8.8 15.4 8.3-5.8 12.3-11.9 8.8-15.4Z"/>',
        'box' => '<path d="m12 3 9 5v9l-9 5-9-5V8l9-5ZM3 8l9 5 9-5M12 13v9M7 5.8l9 5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="m16 3 5 5-12 12-6 1 1-6L16 3ZM14 5l5 5"/>',
        'logout' => '<path d="M9 4H4v16h5M9 12h12m-5-5 5 5-5 5"/>',
        'shield' => '<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4Z"/><path d="m8 12 3 3 5-6"/>',
    ];
    return '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['box']) . '</svg>';
}
function view(string $template, array $data = [], int $status = 200): void {
    http_response_code($status);
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/app/Views/' . $template . '.php';
    $content = ob_get_clean();
    require ROOT . '/app/Views/layout.php';
}
function abort_page(int $status, string $message): never { view('error', ['title' => (string) $status, 'message' => $message, 'code' => $status], $status); exit; }
function require_role(string ...$roles): array {
    $user = current_user();
    if (!$user) { flash('Inicia sesión para continuar.', 'info'); redirect('/ingresar'); }
    if (!in_array($user['role'], $roles, true)) abort_page(403, 'Tu cuenta no tiene permiso para realizar esta operación.');
    return $user;
}
function valid_id(mixed $value): int {
    if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) throw new DomainException('El identificador no es válido.');
    return (int) $value;
}
function input(string $key, string $default = ''): string { $v = $_POST[$key] ?? $default; return is_string($v) ? trim($v) : $default; }
function password_error(string $password): ?string { return strlen($password) < 10 || strlen($password) > 72 ? 'La contraseña debe tener entre 10 y 72 bytes (usa al menos 10 caracteres).' : null; }
