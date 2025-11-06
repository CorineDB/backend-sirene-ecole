<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentValidatedNotification extends Notification
{
    use Queueable;

    protected array $paymentDetails;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $paymentDetails)
    {
        $this->paymentDetails = $paymentDetails;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database']; // Store in database
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('Un nouveau paiement a été validé.')
                    ->action('Voir le paiement', url('/')) // TODO: Link to payment details
                    ->line('Merci d\'utiliser notre application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => 'Nouveau paiement validé',
            'message' => "Un paiement de {$this->paymentDetails['montant']} a été validé pour l'abonnement {$this->paymentDetails['abonnement_id']}.",
            'payment_id' => $this->paymentDetails['payment_id'] ?? null,
            'abonnement_id' => $this->paymentDetails['abonnement_id'],
            'montant' => $this->paymentDetails['montant'],
            'type' => 'payment_validated',
        ];
    }
}
