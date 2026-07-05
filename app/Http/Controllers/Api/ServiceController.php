<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');
        $query = Service::where('is_active', true)->with('variants');
        
        if ($category) {
            $query->where('category', $category);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $service = Service::with('variants')->findOrFail($id);
        return response()->json($service);
    }
}
