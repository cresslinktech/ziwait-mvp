<?php

namespace App\Domain\Orders\Jobs;

use App\Domain\Notifications\NotificationLog;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Notifications\OrderPlacedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Alerts every authorised team member, one channel at a time, and records the outcome of each
 * attempt. One failing recipient or channel never blocks the others.
 */
class SendOrderAlerts implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with('branch.business', 'items')->find($this->orderId);
        if (! $order) {
            return;
        }

        foreach ($this->recipients($order) as $user) {
            // New-order alerts are push notifications. Email is only a fallback for team members
            // who have not enabled push on any device, so nobody silently misses an order.
            $channels = $user->pushSubscriptions()->exists() ? [WebPushChannel::class] : ['mail'];

            foreach ($channels as $channel) {
                $name = $channel === 'mail' ? 'mail' : 'webpush';
                try {
                    Notification::sendNow($user, new OrderPlacedNotification($order), [$channel]);
                    $this->log($order, $user, $name, 'sent');
                } catch (\Throwable $e) {
                    report($e);
                    $this->log($order, $user, $name, 'failed', $e->getMessage());
                }
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function recipients(Order $order)
    {
        return User::query()
            ->whereNull('disabled_at')
            ->whereHas('businessMemberships', fn ($q) => $q->where('business_id', $order->business_id))
            ->get()
            ->filter(fn (User $u) => $u->canInBranch($order->branch, 'orders'));
    }

    private function log(Order $order, User $user, string $channel, string $status, ?string $error = null): void
    {
        NotificationLog::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'channel' => $channel,
            'status' => $status,
            'error' => $error ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
