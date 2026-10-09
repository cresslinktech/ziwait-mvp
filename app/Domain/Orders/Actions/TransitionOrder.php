<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionOrder
{
    public function __invoke(Order $order, OrderStatus $to, User $by, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $note) {
            // Re-read under lock so two staff members cannot both apply a transition from the same state.
            $current = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $current->status->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "An order that is {$current->status->label()} cannot be changed to {$to->label()}.",
                ]);
            }

            $from = $current->status;
            $current->forceFill(['status' => $to])->save();
            $current->history()->make()->forceFill([
                'order_id' => $current->id,
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => $by->id,
                'note' => $note,
            ])->save();

            return $current;
        });
    }
}
