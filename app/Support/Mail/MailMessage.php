<?php

namespace App\Support\Mail;

use Illuminate\Notifications\Messages\MailMessage as BaseMailMessage;

class MailMessage extends BaseMailMessage
{
    /**
     * Create a new mail message instance with default logo.
     *
     * @param  string|null  $template  Template name ('default' or 'minimal')
     */
    public static function withLogo(?string $template = null): static
    {
        $instance = new static;
        $logo = asset('logos/mazelootPrimaryLogo.svg');
        $instance->logo = $logo;

        $template = $template ?? config('mail.template', 'default');

        // Use custom markdown template if not default
        if ($template === 'minimal') {
            $instance->markdown('emails.minimal', ['logo' => $logo]);
        }

        return $instance;
    }
}
