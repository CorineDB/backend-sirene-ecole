<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class MissionCompletionNotification extends Notification
{
    use Queueable;

    protected array $missionDetails;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $missionDetails)
    {
        $this->missionDetails = $missionDetails;
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
                    ->line("La mission {$this->missionDetails['numero_ordre']} est terminée. Veuillez laisser votre avis.")
                    ->action('Donner votre avis', url('/')) // TODO: Link to feedback form
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
            'title' => 'Mission Terminée - Votre Avis',
            'message' => "La mission {$this->missionDetails['numero_ordre']} est terminée. Veuillez laisser votre avis.",
            'ordre_mission_id' => $this->missionDetails['id'],
            'numero_ordre' => $this->missionDetails['numero_ordre'],
            'ecole_nom' => $this->missionDetails['ecole_nom'],
            'type' => 'mission_completion',
        ];
    }
}
