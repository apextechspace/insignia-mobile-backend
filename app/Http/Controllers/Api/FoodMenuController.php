<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class FoodMenuController extends Controller
{
    public function index(Request $request)
    {
        $serviceId = $request->query('service_id');
        
        $query = MenuCategory::where('is_active', true)
            ->with(['menuItems' => function($q) {
                $q->where('is_available', true);
            }]);

        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        $categories = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    public function categories()
    {
        $categories = MenuCategory::where('is_active', true)->get();
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }
}
