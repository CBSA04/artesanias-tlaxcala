<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Product;
use App\Core\Database as DB;

final class CatalogController {
    public function index(): void {
        $filters = [];
        if (!isset($_GET['category']) && isset($_GET['active_category'])) $_GET['category'] = $_GET['active_category'];
        foreach (['q', 'category', 'sort', 'page'] as $key) $filters[$key] = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        $data = Product::catalog($filters);
        $totals = DB::one('SELECT COUNT(*) AS products, COUNT(DISTINCT municipality) AS places FROM products WHERE published=1 AND seller_id IN (SELECT id FROM users WHERE active=1 AND role=\'vendedor\')');
        \view('catalog', $data + ['filters' => $filters, 'totals' => $totals, 'categories' => Product::CATEGORIES, 'title' => 'Artesanías con origen']);
    }
    public function show(int $id): void {
        $product = Product::find($id);
        if (!$product) \abort_page(404, 'Este producto no está disponible en el catálogo.');
        $related = DB::all('SELECT * FROM products WHERE published=1 AND seller_id IN (SELECT id FROM users WHERE active=1 AND role=\'vendedor\') AND category=? AND id<>? ORDER BY id DESC LIMIT 3', [$product['category'], $id]);
        \view('product', ['product' => $product, 'related' => $related, 'title' => $product['name']]);
    }
    public function about(): void { \view('about', ['title' => 'El origen nos une']); }
}
