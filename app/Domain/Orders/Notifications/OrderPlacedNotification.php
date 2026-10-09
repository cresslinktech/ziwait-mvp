<?php

namespace App\Domain\Orders\Notifications;

use App\Domain\Orders\Models\Order;
use App\Support\MoneyFormat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderPlacedNotification extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return $notifiable->pushSubscriptions()->exists() ? [WebPushChannel::class] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order->loadMissing('items', 'branch');
        $customer = $o->table_number ? 'Table '.$o->table_number : $o->customer_name;
        if (! $o->table_number && $o->customer_phone) {
            $customer .= ' ('.$o->customer_phone.')';
        }

        $mail = (new MailMessage)
            ->subject("New order {$o->reference} – {$o->branch->name}")
            ->line("{$customer} placed an order at {$o->branch->name}.");

        foreach ($o->items as $item) {
            $mail->line("{$item->quantity} × {$item->name}");
        }

        $mail->line('Total: '.MoneyFormat::display($o->total_minor, $o->currency).' (payment is handled directly with the customer)');

        if ($o->notes) {
            $mail->line('Customer note: '.$o->notes);
        }

        return $mail->action('Open order', route('orders.show', $o));
    }

    public function toWebPush(object $notifiable, $notification = null): WebPushMessage
    {
        $o = $this->order->loadMissing('branch');

        return (new WebPushMessage)
            ->title("New order {$o->reference}")
            ->body("{$o->branch->name} · ".MoneyFormat::display($o->total_minor, $o->currency))
            ->tag('order-'.$o->id)
            ->data(['url' => route('orders.show', $o)]);
    }
}
