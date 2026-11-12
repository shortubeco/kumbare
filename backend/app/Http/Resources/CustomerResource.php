<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Customer $c */
        $c = $this->resource;

        return [
            'id' => $c->id,
            'customer_no' => $c->customer_no,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'phone' => $c->phone,
            'email' => $c->email,
            'birth_date' => $c->birth_date?->toDateString(),
            'city' => $c->city ? ['id' => $c->city->id, 'name' => $c->city->name] : null,
            'district' => $c->district ? ['id' => $c->district->id, 'name' => $c->district->displayName()] : null,
            'display_name_mode' => $c->display_name_mode,
            'public_name' => $c->publicName(),
        ];
    }
}
