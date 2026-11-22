<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $search = $request->string('q')->trim()->value();

        $products = Product::query()
            ->when($search, function ($query, $term): void {
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'archived', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'nextSku' => Product::nextSku(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $sku = filled($data['sku'] ?? null) ? $data['sku'] : Product::nextSku();

        Product::create([
            'name' => $data['name'],
            'sku' => $sku,
            'price' => $data['price'],
            'stock_quantity' => $data['stock_quantity'],
            'is_active' => true,
        ]);

        return redirect()->route('products.index')->with('success', 'Item produk (buku/alat terapi) berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'price' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $product->update([
            'name' => $data['name'],
            'sku' => $data['sku'],
            'price' => $data['price'],
            'stock_quantity' => $data['stock_quantity'],
        ]);

        return redirect()->route('products.index')->with('success', 'Data produk berhasil diperbarui.');
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        $message = $product->is_active
            ? 'Produk berhasil diaktifkan kembali.'
            : 'Produk diarsipkan dan tidak muncul di pilihan kasir.';

        return redirect()->route('products.index')->with('success', $message);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $hasSales = DB::table('sale_lines')->where('product_id', $product->id)->exists();

        if ($hasSales) {
            $product->update(['is_active' => false]);

            return redirect()->route('products.index')->with('success', 'Produk memiliki riwayat transaksi, status diubah menjadi diarsipkan.');
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus.');
    }
}
