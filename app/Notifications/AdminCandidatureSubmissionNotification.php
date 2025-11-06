<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AdminCandidatureSubmissionNotification extends Notification
{
    use Queueable;

    protected array $candidatureDetails;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $candidatureDetails)
    {
        $this->candidatureDetails = $candidatureDetails;
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
                    ->line("Une nouvelle candidature a été soumise par le technicien {$this->candidatureDetails['technicien_nom']} pour l'ordre de mission {$this->candidatureDetails['numero_ordre']}.")
                    ->action('Voir la candidature', url('/')) // TODO: Link to candidature details
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
            'title' => 'Nouvelle Candidature Soumise',
            'message' => "Une nouvelle candidature a été soumise par le technicien {$this->candidatureDetails['technicien_nom']} pour l'ordre de mission {$this->candidatureDetails['numero_ordre']}.",
            'technicien_id' => $this->candidatureDetails['technicien_id'],
            'technicien_nom' => $this->candidatureDetails['technicien_nom'],
            'ordre_mission_id' => $this->candidatureDetails['ordre_mission_id'],
            'numero_ordre' => $this->candidatureDetails['numero_ordre'],
            'type' => 'admin_candidature_submission',
        ];
    }
}
