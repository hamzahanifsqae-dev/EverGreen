<?php

namespace App\Mail;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ColdStorageActionAlertsMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *     temperature_count: int,
     *     bill_count: int,
     *     reservation_count: int,
     *     open_count: int,
     *     temperature: list<array<string, mixed>>,
     *     bills: list<array<string, mixed>>,
     *     reservations: list<array<string, mixed>>,
     *     currency: string,
     *     panel_url: ?string
     * }  $digest
     */
    public function __construct(
        public Merchant $merchant,
        public array $digest,
    ) {}

    public function envelope(): Envelope
    {
        $open = (int) ($this->digest['open_count'] ?? 0);

        return new Envelope(
            subject: $open > 0
                ? "Cold storage action alerts ({$open}) — {$this->merchant->name}"
                : "Cold storage action alerts — {$this->merchant->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cold-storage-action-alerts',
            with: [
                'merchant' => $this->merchant,
                'digest' => $this->digest,
            ],
        );
    }
}
