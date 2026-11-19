<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database as DB;

final class AdminController {
    public function index(): void {
        \require_role('administrador');
        $search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
        $params = []; $where = '';
        if ($search !== '') { $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%'; $where = " WHERE name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!'"; $params = [$term, $term]; }
        $page = max(1, min(10000, (int) ($_GET['page'] ?? 1)));
        $count = (int) DB::one('SELECT COUNT(*) AS n FROM users' . $where, $params)['n'];
        $users = DB::all('SELECT id, name, email, role, active, created_at FROM users' . $where . ' ORDER BY id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30), $params);
        $stats = DB::one("SELECT COUNT(*) AS users, COALESCE(SUM(active),0) AS active, COALESCE(SUM(CASE WHEN role='vendedor' AND active=1 THEN 1 ELSE 0 END),0) AS sellers FROM users");
        \view('admin', compact('users', 'search', 'page', 'count', 'stats') + ['title' => 'Administración']);
    }
    public function form(?int $id = null): void {
        \require_role('administrador');
        $user = $id ? DB::one('SELECT id, name, email, role, active FROM users WHERE id=?', [$id]) : null;
        if ($id && !$user) \abort_page(404, 'La cuenta no existe.');
        \view('user-form', ['user' => $user, 'title' => $id ? 'Editar cuenta' : 'Crear cuenta']);
    }
    private function protectAdmin(?array $target, string $role, int $active, int $actor): void {
        $db = DB::connection();
        $admins = DB::all("SELECT id FROM users WHERE role='administrador' AND active=1 ORDER BY id" . DB::lock($db));
        if ($target && $target['role'] === 'administrador' && $target['active'] && ($role !== 'administrador' || !$active)) {
            if (count($admins) < 2) throw new \DomainException('Debe permanecer al menos un administrador activo.');
        }
        if ($target && (int) $target['id'] === $actor && ($role !== 'administrador' || !$active)) throw new \DomainException('Usa otra cuenta administrativa para cambiar tu propio rol o dar de baja tu acceso.');
    }
    public function save(?int $id = null): void {
        $actor = \require_role('administrador');
        $user = $id ? DB::one('SELECT id, name, email, role, active FROM users WHERE id=?', [$id]) : null;
        if ($id && !$user) \abort_page(404, 'La cuenta no existe.');
        $old = ['name' => \input('name'), 'email' => mb_strtolower(\input('email')), 'role' => \input('role'), 'active' => \input('active') === '1' ? 1 : 0];
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $db = DB::connection();
        try {
            if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) throw new \DomainException('El nombre debe tener entre 2 y 100 caracteres.');
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 120) throw new \DomainException('Escribe un correo válido.');
            if (!in_array($old['role'], ['cliente', 'vendedor', 'administrador'], true)) throw new \DomainException('Selecciona un rol válido.');
            if (!$id || $password !== '') { if ($error = \password_error($password)) throw new \DomainException($error); }
            $db->beginTransaction();
            $this->protectAdmin($user, $old['role'], $old['active'], (int) $actor['id']);
            if (DB::one('SELECT id FROM users WHERE email=? AND id<>?', [$old['email'], $id ?? 0])) throw new \DomainException('Este correo pertenece a otra cuenta.');
            if ($id) {
                DB::run('UPDATE users SET name=?, email=?, role=?, active=? WHERE id=?', [...array_values($old), $id]);
                if ($password !== '') DB::run('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                DB::run('INSERT INTO users (name, email, role, active, password_hash, created_at) VALUES (?,?,?,?,?,?)', [...array_values($old), password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
                $id = (int) $db->lastInsertId();
            }
            DB::run('INSERT INTO audit_log (actor_id, action, subject_id, created_at) VALUES (?,?,?,?)', [$actor['id'], $user ? 'user.updated' : 'user.created', $id, date('Y-m-d H:i:s')]);
            $db->commit(); \flash('La cuenta se guardó correctamente.'); \redirect('/admin');
        } catch (\DomainException | \PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = $e instanceof \DomainException ? $e->getMessage() : 'No se pudo guardar la cuenta. Revisa que el correo no esté registrado.';
            \view('user-form', ['user' => $user, 'old' => $old, 'title' => $user ? 'Editar cuenta' : 'Crear cuenta', 'error' => $message], 422);
        }
    }
    public function deleteForm(int $id): void {
        \require_role('administrador'); $user = DB::one('SELECT id, name, email, role, active FROM users WHERE id=?', [$id]);
        if (!$user) \abort_page(404, 'La cuenta no existe.');
        \view('user-delete', ['user' => $user, 'title' => 'Dar de baja cuenta']);
    }
    public function delete(int $id): void {
        $actor = \require_role('administrador'); $db = DB::connection();
        try {
            $db->beginTransaction();
            $user = DB::one('SELECT id, role, active FROM users WHERE id=?', [$id]);
            if (!$user) throw new \DomainException('La cuenta no existe.');
            $this->protectAdmin($user, $user['role'], 0, (int) $actor['id']);
            DB::run('UPDATE users SET active=0 WHERE id=?', [$id]);
            DB::run('INSERT INTO audit_log (actor_id, action, subject_id, created_at) VALUES (?,?,?,?)', [$actor['id'], 'user.deactivated', $id, date('Y-m-d H:i:s')]);
            $db->commit(); \flash('La cuenta quedó inactiva. Su historial se conservó.');
        } catch (\DomainException $e) { if ($db->inTransaction()) $db->rollBack(); \flash($e->getMessage(), 'error'); }
        \redirect('/admin');
    }
}
