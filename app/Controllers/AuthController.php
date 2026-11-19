<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database as DB;

final class AuthController {
    public function loginForm(): void { if (\current_user()) \redirect('/cuenta'); \view('auth', ['mode' => 'login', 'title' => 'Bienvenido de nuevo']); }
    public function registerForm(): void { if (\current_user()) \redirect('/cuenta'); \view('auth', ['mode' => 'register', 'title' => 'Crea tu cuenta']); }
    private function keys(string $email): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return [hash('sha256', 'ip:' . $ip), hash('sha256', 'pair:' . $ip . ':' . $email)];
    }
    public function login(): void {
        $email = mb_strtolower(\input('email')); $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $keys = $this->keys($email); $now = time();
        foreach ($keys as $i => $key) {
            $row = DB::one('SELECT attempts, window_start FROM login_attempts WHERE attempt_key=?', [$key]);
            if ($row && $now - (int) $row['window_start'] < 900 && (int) $row['attempts'] >= ($i === 0 ? 20 : 5)) {
                \view('auth', ['mode' => 'login', 'title' => 'Ingresar', 'error' => 'Demasiados intentos. Espera 15 minutos antes de volver a intentar.', 'old' => ['email' => $email]], 429); return;
            }
        }
        $user = DB::one('SELECT * FROM users WHERE email=?', [$email]);
        $hash = $user['password_hash'] ?? '$2y$12$9UAg4O4O4O4O4O4O4O4O4e7XBDnXITK.AiYX.qEp4vH7CEqj6Tn7G';
        if (!password_verify($password, $hash) || !$user || !(int) $user['active']) {
            foreach ($keys as $key) {
                if (\env('DB_CONNECTION', 'mysql') === 'sqlite') {
                    DB::run('INSERT INTO login_attempts (attempt_key, attempts, window_start) VALUES (?,1,?) ON CONFLICT(attempt_key) DO UPDATE SET attempts=CASE WHEN window_start<? THEN 1 ELSE attempts+1 END, window_start=CASE WHEN window_start<? THEN excluded.window_start ELSE window_start END', [$key, $now, $now - 900, $now - 900]);
                } else {
                    DB::run('INSERT INTO login_attempts (attempt_key, attempts, window_start) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start<?,1,attempts+1), window_start=IF(window_start<?,VALUES(window_start),window_start)', [$key, $now, $now - 900, $now - 900]);
                }
            }
            \view('auth', ['mode' => 'login', 'title' => 'Ingresar', 'error' => 'El correo o la contraseña no son correctos, o la cuenta no está activa.', 'old' => ['email' => $email]], 422); return;
        }
        DB::run('DELETE FROM login_attempts WHERE attempt_key=?', [$keys[1]]);
        session_regenerate_id(true); $_SESSION = ['user_id' => (int) $user['id'], 'last_seen' => time()];
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) DB::run('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        \flash('Qué gusto verte, ' . explode(' ', $user['name'])[0] . '.'); \redirect('/cuenta');
    }
    public function register(): void {
        $old = ['name' => \input('name'), 'email' => mb_strtolower(\input('email'))];
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
        $error = null;
        if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) $error = 'Escribe tu nombre, entre 2 y 100 caracteres.';
        elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 120) $error = 'Escribe un correo electrónico válido.';
        elseif ($problem = \password_error($password)) $error = $problem;
        elseif ($password !== $confirm) $error = 'Las contraseñas no coinciden.';
        elseif (DB::one('SELECT id FROM users WHERE email=?', [$old['email']])) $error = 'Este correo ya tiene una cuenta. Puedes iniciar sesión.';
        if ($error) { \view('auth', ['mode' => 'register', 'title' => 'Crea tu cuenta', 'old' => $old, 'error' => $error], 422); return; }
        try {
            DB::run('INSERT INTO users (name, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, ?)', [$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), 'cliente', date('Y-m-d H:i:s')]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            \view('auth', ['mode' => 'register', 'title' => 'Crea tu cuenta', 'old' => $old, 'error' => 'Este correo ya tiene una cuenta.'], 422); return;
        }
        $id = (int) DB::connection()->lastInsertId();
        session_regenerate_id(true); $_SESSION = ['user_id' => $id, 'last_seen' => time()];
        \flash('Tu cuenta está lista. Encuentra una pieza que cuente tu historia.'); \redirect('/');
    }
    public function logout(): void {
        $_SESSION = []; session_regenerate_id(true); \flash('Cerraste tu sesión correctamente.', 'info'); \redirect('/');
    }
    public function account(): void {
        $user = \require_role('cliente', 'vendedor', 'administrador');
        if ($user['role'] === 'vendedor') \redirect('/vendedor');
        if ($user['role'] === 'administrador') \redirect('/admin');
        $stats = DB::one('SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS amount FROM orders WHERE user_id=?', [$user['id']]);
        $orders = DB::all('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 3', [$user['id']]);
        \view('account', compact('user', 'stats', 'orders') + ['title' => 'Mi cuenta']);
    }
}
