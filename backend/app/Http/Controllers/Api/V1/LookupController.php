<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function cities(): JsonResponse
    {
        $cities = City::query()->orderBy('name')->get(['id', 'name', 'slug', 'plate_code']);

        return response()->json(['data' => $cities->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'plate_code' => $c->plate_code,
        ])]);
    }

    public function districts(City $city): JsonResponse
    {
        $districts = District::query()->where('city_id', $city->id)->orderBy('name')->get();

        return response()->json(['data' => $districts->map(fn (District $d) => [
            'id' => $d->id,
            'name' => $d->name === 'Merkez' ? $city->name.' Merkez' : $d->name,
            'slug' => $d->slug,
        ])]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);

        return response()->json(['data' => $categories]);
    }
}
