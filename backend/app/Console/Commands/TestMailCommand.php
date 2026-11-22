<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Sends one plain test email right away (not through the queue), so a mail setup can be checked from the server
 * Terminal: the mailer and sender in use are printed first, and a failure shows the provider's own error message.
 */
#[Signature('biponon:test-mail {email : Where to send the test email}')]
#[Description('Send a test email with the current mail settings')]
class TestMailCommand extends Command
{
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error("{$email} is not a valid email address.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $this->components->twoColumnDetail('Mailer', $mailer);
        $this->components->twoColumnDetail('From', $from.' ('.config('mail.from.name').')');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->components->warn("The {$mailer} mailer doesn't send real email; set MAIL_MAILER in .env, then run php artisan optimize.");
        }

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').' ('.config('app.url').'). If it reached your inbox, email is set up.',
                fn (Message $message) => $message->to($email)->subject(config('app.name').' test email'),
            );
        } catch (Throwable $exception) {
            $this->components->error('Sending failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Sent to {$email}. Check the inbox and the spam folder.");

        return self::SUCCESS;
    }
}
