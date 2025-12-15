<?php

namespace App\Notifications;

use Illuminate\Support\Collection;

use App\Notifications\Concerns\ManyMailNotification;
use App\Notifications\Concerns\MailFormatter;
use App\Notifications\Concerns\MailReplyTo;

class SupplierOrderShipping extends ManyMailNotification
{
    use MailFormatter, MailReplyTo;

    private $gas;
    private $order;

    public function __construct($gas, $order)
    {
        $this->gas = $gas;
        $this->order = $order;
    }

    public function toMail($notifiable)
    {
        $message = $this->initMailMessage($notifiable);

        /*
            Nella modalità Multi-GAS un ordine può potenzialmente essere
            assegnato a molteplici GAS, che possono avere configurazioni diverse
            per la formattazione delle mail
        */
        $notifiable->setRelation('gas', new Collection([$this->gas]));

        $message = $this->formatMail($message, $notifiable, 'supplier_summary', [
            'supplier_name' => $this->order->supplier->name,
            'order_number' => $this->order->number,
            'download_link' => publicGateGetLink('odoc', 'show', [
                'id' => $this->order->id,
            ]),
        ]);

        $users = everybodyCan('supplier.orders', $this->order->supplier);
        foreach ($users as $referent) {
            if (! empty($referent->email)) {
                $message = $message->cc($referent->email);
                $referent->messageAll($message);
            }
        }

        $message = $this->guessReplyTo($message, $this->order);
        return $message;
    }
}
