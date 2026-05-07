<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order slip {{ $order->order_number }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f8;
            color: #111827;
            margin: 0;
            padding: 24px;
        }

        .toolbar {
            margin: 0 auto 16px;
            max-width: 820px;
            text-align: right;
        }

        .toolbar button {
            background: #111827;
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            padding: 10px 14px;
        }

        .slip {
            background: #fff;
            border: 1px solid #d1d5db;
            margin: 0 auto;
            max-width: 820px;
            padding: 28px;
        }

        .header,
        .grid,
        .totals {
            display: grid;
            gap: 16px;
            grid-template-columns: 1fr 1fr;
        }

        .header {
            align-items: start;
            border-bottom: 2px solid #111827;
            padding-bottom: 18px;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        h1 {
            font-size: 24px;
        }

        h2 {
            font-size: 13px;
            letter-spacing: .04em;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
        }

        .section {
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 0;
        }

        .line {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            padding: 10px 8px;
            text-align: left;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            text-transform: uppercase;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            margin-left: auto;
            max-width: 360px;
        }

        .total {
            border-top: 2px solid #111827;
            font-weight: 700;
            padding-top: 8px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .slip {
                border: 0;
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print order slip</button>
    </div>

    <main class="slip">
        <section class="header">
            <div>
                <h1>{{ $order->store?->name ?? 'Store' }}</h1>
                <p class="muted">Order slip</p>
            </div>
            <div>
                <div class="line">
                    <strong>Order</strong>
                    <span>{{ $order->order_number }}</span>
                </div>
                <div class="line">
                    <strong>Status</strong>
                    <span>{{ $order->status->getLabel() }}</span>
                </div>
                <div class="line">
                    <strong>Date</strong>
                    <span>{{ $order->created_at?->format('Y-m-d H:i') }}</span>
                </div>
            </div>
        </section>

        <section class="section grid">
            <div>
                <h2>Customer</h2>
                <p>{{ $order->customer?->full_name }}</p>
                <p>{{ $order->customer?->phone }}</p>
            </div>
            <div>
                <h2>Delivery</h2>
                <p>{{ $order->delivery_type->getLabel() }}</p>
                <p>{{ $order->wilaya?->name_fr }} / {{ $order->commune?->name_fr }}</p>
                <p>{{ $order->shipping_address }}</p>
            </div>
        </section>

        <section class="section">
            <h2>Items</h2>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="amount">Qty</th>
                        <th class="amount">Unit</th>
                        <th class="amount">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ $item->product_sku ?? '-' }}</td>
                            <td class="amount">{{ $item->quantity }}</td>
                            <td class="amount">{{ number_format($item->unit_price_minor / 100, 2) }} {{ $order->currency }}</td>
                            <td class="amount">{{ number_format($item->total_minor / 100, 2) }} {{ $order->currency }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No order items were recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="section">
            <div class="totals">
                <div>
                    <div class="line">
                        <span>Subtotal</span>
                        <span>{{ number_format($order->subtotal_minor / 100, 2) }} {{ $order->currency }}</span>
                    </div>
                    <div class="line">
                        <span>Shipping</span>
                        <span>{{ number_format($order->shipping_fee_minor / 100, 2) }} {{ $order->currency }}</span>
                    </div>
                    <div class="line">
                        <span>Discount</span>
                        <span>{{ number_format($order->discount_minor / 100, 2) }} {{ $order->currency }}</span>
                    </div>
                    <div class="line total">
                        <span>Total</span>
                        <span>{{ number_format($order->total_minor / 100, 2) }} {{ $order->currency }}</span>
                    </div>
                </div>
            </div>
        </section>

        @if ($order->customer_note)
            <section class="section">
                <h2>Customer note</h2>
                <p>{{ $order->customer_note }}</p>
            </section>
        @endif
    </main>
</body>
</html>
