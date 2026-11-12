<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Kumbaram: müşterinin şube bazındaki kumbaraları. */
class WalletController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $wallets = Wallet::query()
            ->where('customer_id', $request->user()->id)
            ->with('branch.business')
            ->orderByDesc('balance')
            ->orderBy('id')
            ->get();

        return WalletResource::collection($wallets);
    }

    public function show(Request $request, int $wallet): WalletResource
    {
        return new WalletResource($this->find($request, $wallet));
    }

    public function transactions(Request $request, int $wallet): AnonymousResourceCollection
    {
        $w = $this->find($request, $wallet);

        $transactions = $w->transactions()
            ->with('campaign')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        return TransactionResource::collection($transactions);
    }

    private function find(Request $request, int $id): Wallet
    {
        $wallet = Wallet::query()
            ->where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->with('branch.business')
            ->first();

        if (! $wallet) {
            throw DomainException::notFound('Kumbara bulunamadı.');
        }

        return $wallet;
    }
}
