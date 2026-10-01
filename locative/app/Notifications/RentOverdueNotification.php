<?php

namespace App\Notifications;

use App\Models\Rent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Notification;

class RentOverdueNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable;

    // Ton qui se durcit avec le retard — un simple rappel à J+3, une mise
    // en demeure plus formelle à J+15.
    public function __construct(private readonly Rent $rent, private readonly string $stage)
    {
    }

    public function via($notifiable): array
    {
        // Dans l'application toujours ; SMS en plus aux paliers sérieux si le
        // locataire a un numéro (pilote SMS configurable, "log" par défaut).
        $channels = ['mail', 'database'];
        if (in_array($this->stage, ['j7', 'j15'], true) && $notifiable->phone) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => "rent_overdue_{$this->stage}",
            'params' => [
                'property' => $this->rent->contract->property->name ?? '',
                'month' => $this->rent->month,
                'amount' => $this->rent->totalDue(),
                'days' => $this->rent->daysLate(),
            ],
            'link' => '/rents',
        ];
    }

    public function toSms($notifiable): string
    {
        return __('notifications.rent_overdue.sms', [
            'app' => config('app.name'),
            'property' => $this->rent->contract->property->name ?? '',
            'month' => $this->rent->month,
            'amount' => number_format($this->rent->totalDue(), 0, ',', ' '),
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        // Rendu dans la langue du locataire : Laravel applique
        // User::preferredLocale() avant d'appeler toMail().
        $rent = $this->rent;
        $params = [
            'property' => $rent->contract->property->name ?? '',
            'month' => $rent->month,
            'days' => $rent->daysLate(),
        ];
        $amount = number_format($rent->totalDue(), 0, ',', ' ') . ' FCFA';

        $message = (new MailMessage)
            ->subject(__("notifications.rent_overdue.subject.{$this->stage}", $params))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__("notifications.rent_overdue.{$this->stage}", $params));

        if ($this->stage === 'j3') {
            $message->line(__('notifications.rent_overdue.amount', ['amount' => $amount]))
                ->line(__('notifications.rent_overdue.please_pay'));
        } else {
            $message->line(__('notifications.rent_overdue.amount_with_penalty', ['amount' => $amount]))
                ->line(__($this->stage === 'j15'
                    ? 'notifications.rent_overdue.formal_notice'
                    : 'notifications.rent_overdue.please_settle'));
        }

        return $message->line(__('notifications.signature'));
    }
}
