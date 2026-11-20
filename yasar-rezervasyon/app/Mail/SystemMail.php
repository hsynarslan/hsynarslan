<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tüm sistem e-postaları. Metinler çeviri anahtarı olarak taşınır ve alıcının dilinde işlenir.
 * Satırlar: [anahtar, [yer tutucular]].
 */
class SystemMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectKey,
        public array $lines = [],
        public ?int $reservationId = null,
        public ?string $actionKey = null,
        public ?string $actionUrl = null,
        public array $subjectReplace = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->subjectKey, $this->subjectReplace).' · '.__('Yaşar Üniversitesi Rezervasyon'));
    }

    public function content(): Content
    {
        $reservation = $this->reservationId
            ? Reservation::with('facility', 'activity', 'units', 'team', 'block', 'equipmentItems.equipment')->find($this->reservationId)
            : null;

        return new Content(markdown: 'mail.system', with: [
            'lines' => array_map(fn ($l) => __($l[0], $l[1] ?? []), $this->lines),
            'reservation' => $reservation,
            'actionText' => $this->actionKey ? __($this->actionKey) : null,
            'actionUrl' => $this->actionUrl,
        ]);
    }
}
