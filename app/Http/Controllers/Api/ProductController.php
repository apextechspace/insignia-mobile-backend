<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');
        $query = Product::query();

        if ($category && $category !== 'All') {
            $query->where('category', $category);
        }

        return response()->json($query->get());
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }
}
