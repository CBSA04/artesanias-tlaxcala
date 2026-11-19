<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database as DB;

final class Product {
    public const CATEGORIES = ['textiles' => 'Textiles', 'ceramica' => 'Cerámica', 'madera' => 'Madera', 'decoracion' => 'Decoración'];
    public static function find(int $id, bool $public = true): ?array {
        return DB::one('SELECT p.*, u.name AS seller_name, u.active AS seller_active, u.role AS seller_role FROM products p JOIN users u ON u.id=p.seller_id WHERE p.id=?' . ($public ? " AND p.published=1 AND u.active=1 AND u.role='vendedor'" : ''), [$id]);
    }
    public static function catalog(array $filters): array {
        $where = ["p.published=1", "p.seller_id IN (SELECT id FROM users WHERE active=1 AND role='vendedor')"]; $params = [];
        if (($filters['category'] ?? '') !== '' && isset(self::CATEGORIES[$filters['category']])) { $where[] = 'p.category=?'; $params[] = $filters['category']; }
        if (($filters['q'] ?? '') !== '') {
            $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($filters['q'], 0, 100)) . '%';
            $where[] = "(p.name LIKE ? ESCAPE '!' OR p.material LIKE ? ESCAPE '!' OR p.municipality LIKE ? ESCAPE '!')";
            array_push($params, $term, $term, $term);
        }
        $sql = implode(' AND ', $where);
        $count = (int) DB::one('SELECT COUNT(*) AS n FROM products p WHERE ' . $sql, $params)['n'];
        $page = max(1, min((int) ($filters['page'] ?? 1), max(1, (int) ceil($count / 12))));
        $order = ['recent' => 'p.id DESC', 'price_asc' => 'p.price ASC, p.id ASC', 'price_desc' => 'p.price DESC, p.id ASC', 'name' => 'p.name ASC'][$filters['sort'] ?? 'recent'] ?? 'p.id DESC';
        $products = DB::all('SELECT p.*, u.name AS seller_name, u.active AS seller_active, u.role AS seller_role FROM products p JOIN users u ON u.id=p.seller_id WHERE ' . $sql . ' ORDER BY ' . $order . ' LIMIT 12 OFFSET ' . (($page - 1) * 12), $params);
        return compact('products', 'count', 'page');
    }
}
