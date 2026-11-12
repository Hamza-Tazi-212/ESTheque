<?php

namespace App\Mail;

use App\Data\OverdueStudentData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OverdueReminder extends Mailable
{
    public function __construct(public readonly OverdueStudentData $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bibliothèque EST Fès — retour de vos exemplaires en retard');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.overdue-reminder');
    }
}
