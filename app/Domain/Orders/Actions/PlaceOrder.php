<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\QrLink;
use App\Domain\Orders\Jobs\SendOrderAlerts;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Places a customer order. Everything the browser sends other than the quantities and table
 * details is ignored: branch comes from the QR token, prices and names from the database.
 * Payment happens outside ZIWAIT, so the order is never marked paid.
 */
class PlaceOrder
{
    public const MAX_QUANTITY = 20;

    /**
    * @param  array{table_number:string, notes?:?string}  $customer
     * @param  array<int|string, int|string>  $quantities  menu_item_id => quantity
     */
    public function __invoke(QrLink $link, array $customer, array $quantities): Order
    {
        $lines = collect($quantities)
            ->mapWithKeys(fn ($qty, $id) => [(int) $id => (int) $qty])
            ->filter(fn ($qty) => $qty > 0);

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['qty' => 'Add at least one item to your order.']);
        }

        if ($lines->contains(fn ($qty) => $qty > self::MAX_QUANTITY)) {
            throw ValidationException::withMessages(['qty' => 'You can order at most '.self::MAX_QUANTITY.' of any one item.']);
        }

        $order = DB::transaction(function () use ($link, $customer, $lines) {
            $branch = $link->branch()->with('business')->first();

            if ($link->revoked_at || ! $branch || ! $branch->is_active || ! $branch->business->isActive() || ! app(\App\Domain\Billing\Actions\EntitlementService::class)->isOperational($branch->business)) {
                throw ValidationException::withMessages(['branch' => 'This branch is not taking orders right now.']);
            }

            $offers = BranchMenuItem::query()
                ->where('branch_id', $branch->id)
                ->whereIn('menu_item_id', $lines->keys())
                ->where('is_visible', true)
                ->where('is_available', true)
                ->whereHas('menuItem', fn ($q) => $q->where('business_id', $branch->business_id)->where('is_active', true)
                    ->whereHas('category', fn ($c) => $c->where('is_active', true)))
                ->with('menuItem')
                ->lockForUpdate()
                ->get()
                ->keyBy('menu_item_id');

            $missing = $lines->keys()->diff($offers->keys());
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['qty' => 'Some items in your cart are no longer available. Please review your order.']);
            }

            $total = 0;
            $order = new Order;
            $order->forceFill([
                'business_id' => $branch->business_id,
                'branch_id' => $branch->id,
                'table_number' => $customer['table_number'],
                'reference' => $this->newReference(),
                'public_token' => Str::random(40),
                'status' => OrderStatus::Submitted,
                'customer_name' => 'Table '.$customer['table_number'],
                'customer_phone' => null,
                'customer_email' => null,
                'notes' => $customer['notes'] ?? null,
                'currency' => $branch->business->currency,
                'total_minor' => 0,
                'placed_at' => now(),
            ])->save();

            foreach ($lines as $itemId => $qty) {
                $offer = $offers[$itemId];
                $lineTotal = $offer->price_minor * $qty;
                $total += $lineTotal;

                $order->items()->make()->forceFill([
                    'menu_item_id' => $itemId,
                    'name' => $offer->menuItem->name,
                    'unit_price_minor' => $offer->price_minor,
                    'quantity' => $qty,
                    'line_total_minor' => $lineTotal,
                ])->save();
            }

            $order->forceFill(['total_minor' => $total])->save();
            $order->history()->make()->forceFill(['order_id' => $order->id, 'from_status' => null, 'to_status' => OrderStatus::Submitted])->save();

            return $order;
        });

        // Notification problems must never fail or roll back an order that is already saved.
        try {
            SendOrderAlerts::dispatch($order->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return $order;
    }

    private function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $ref = 'ZW-';
            for ($i = 0; $i < 8; $i++) {
                $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Order::where('reference', $ref)->exists());

        return $ref;
    }
}
