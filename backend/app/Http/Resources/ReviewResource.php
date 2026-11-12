<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Review $r */
        $r = $this->resource;

        return [
            'id' => $r->id,
            'score' => $r->score,
            'score_label' => $r->score.' / 10',
            'comment' => $r->comment,
            'author' => $r->customer?->publicName(),
            'status' => $this->when($request->user()?->getKey() === $r->customer_id, $r->status),
            'reply' => $r->reply ? [
                'body' => $r->reply->body,
                'created_at' => Support::iso($r->reply->created_at),
            ] : null,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $r->branch->id,
                'display_name' => $r->branch->business->name.' – '.$r->branch->name,
            ]),
            'created_at' => Support::iso($r->created_at),
            'updated_at' => Support::iso($r->updated_at),
        ];
    }
}
