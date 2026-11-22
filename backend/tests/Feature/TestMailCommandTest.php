<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TestMailCommandTest extends TestCase
{
    public function test_it_sends_a_test_email_to_the_given_address(): void
    {
        $this->artisan('biponon:test-mail', ['email' => 'owner@example.com'])
            ->expectsOutputToContain('Sent to owner@example.com')
            ->assertSuccessful();

        /** @var ArrayTransport $transport */
        $transport = Mail::mailer()->getSymfonyTransport();
        $messages = $transport->messages();

        $this->assertCount(1, $messages);
        $this->assertSame('owner@example.com', $messages->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_it_refuses_an_invalid_address(): void
    {
        $this->artisan('biponon:test-mail', ['email' => 'not-an-email'])->assertFailed();

        /** @var ArrayTransport $transport */
        $transport = Mail::mailer()->getSymfonyTransport();

        $this->assertCount(0, $transport->messages());
    }
}
