<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        return view('cart.index', $this->cartData($request));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $request->session()->get('cart', []);
        $newQty = ($cart[$product->id] ?? 0) + $data['quantity'];

        if ($newQty > $product->stock) {
            return back()->withErrors(['quantity' => 'Jumlah melebihi stok yang tersedia.']);
        }

        $cart[$product->id] = $newQty;
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')->with('success', 'Produk ditambahkan ke keranjang.');
    }


    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $cart = $request->session()->get('cart', []);

        if (! array_key_exists($product->id, $cart)) {
            abort(404);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['quantity'] > $product->stock) {
            return back()->withErrors([
                'quantity' => "Stok {$product->name} hanya {$product->stock}."
            ]);
        }

        $cart[$product->id] = $data['quantity'];
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$product->id]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }


    private function cartData(Request $request): array
    {
        $cart = $request->session()->get('cart', []);

        $products = Product::whereIn('id', array_keys($cart))
            ->get()
            ->keyBy('id');

        $items = collect();
        $subtotal = 0;
        $removedUnavailable = false;

        foreach ($cart as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product || ! $product->is_active || $product->stock === 0) {
                unset($cart[$productId]);
                $removedUnavailable = true;
                continue;
            }

            $lineTotal = (float) $product->price * $quantity;
            $subtotal += $lineTotal;

            $items->push(compact('product', 'quantity', 'lineTotal'));
        }

        $request->session()->put('cart', $cart);

        if ($removedUnavailable) {
            $request->session()->flash(
                'error',
                'Produk yang sudah tidak tersedia telah dihapus dari keranjang.'
            );
        }

        return compact('items', 'subtotal');
    }
}
