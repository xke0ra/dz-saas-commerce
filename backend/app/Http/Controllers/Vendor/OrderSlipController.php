<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class OrderSlipController extends Controller
{
    public function __invoke(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load([
            'commune',
            'customer',
            'items',
            'store',
            'wilaya',
        ]);

        return view('vendor.orders.slip', [
            'order' => $order,
        ]);
    }
}
