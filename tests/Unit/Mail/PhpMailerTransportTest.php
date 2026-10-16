<?php

namespace Tests\Unit\Mail;

use App\Mail\PhpMailerTransport;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class PhpMailerTransportTest extends TestCase
{
    /**
     * PHPMailer que no es connecta enlloc: guarda l'últim missatge preparat.
     */
    private function fakeMailer(bool $fail = false): PHPMailer
    {
        return new class(true, $fail) extends PHPMailer
        {
            public bool $sent = false;

            public function __construct(?bool $exceptions, private readonly bool $fail)
            {
                parent::__construct($exceptions);
            }

            public function send(): bool
            {
                if ($this->fail) {
                    throw new Exception('SMTP connect() failed.');
                }

                $this->sent = true;

                return true;
            }
        };
    }

    private function config(): array
    {
        return ['host' => 'smtp.example.org', 'port' => 587, 'encryption' => 'tls', 'username' => 'usuari', 'password' => 'secret', 'timeout' => 10];
    }

    public function test_it_configures_smtp_from_the_mail_config(): void
    {
        $mailer = (new PhpMailerTransport($this->config()))->configure($this->fakeMailer());

        $this->assertSame('smtp', $mailer->Mailer);
        $this->assertSame('smtp.example.org', $mailer->Host);
        $this->assertSame(587, $mailer->Port);
        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $mailer->SMTPSecure);
        $this->assertTrue($mailer->SMTPAuth);
        $this->assertSame('usuari', $mailer->Username);
        $this->assertSame(PHPMailer::CHARSET_UTF8, $mailer->CharSet);
        $this->assertSame(10, $mailer->Timeout);
    }

    public function test_ssl_encryption_uses_smtps(): void
    {
        $mailer = (new PhpMailerTransport([...$this->config(), 'encryption' => 'ssl', 'port' => 465]))->configure($this->fakeMailer());

        $this->assertSame(PHPMailer::ENCRYPTION_SMTPS, $mailer->SMTPSecure);
    }

    public function test_it_sends_the_email_through_phpmailer(): void
    {
        $fake = $this->fakeMailer();
        $transport = new PhpMailerTransport($this->config(), fn () => $fake);

        $email = (new Email)
            ->from(new Address('club@example.org', 'Joventut Sant Pere Màrtir'))
            ->to(new Address('gestor@example.org', 'Gestor'))
            ->bcc('ocult@example.org')
            ->subject('Programació de la jornada')
            ->text('Text pla amb accents: Màrtir, Aleví, Cancel·lat')
            ->html('<p>Programació</p>');

        $transport->send($email);

        $this->assertTrue($fake->sent);
        $this->assertSame('Programació de la jornada', $fake->Subject);
        $this->assertSame('club@example.org', $fake->From);
        $this->assertSame('Joventut Sant Pere Màrtir', $fake->FromName);
        $this->assertSame([['gestor@example.org', 'Gestor']], $fake->getToAddresses());
        $this->assertSame('ocult@example.org', $fake->getBccAddresses()[0][0]);
        $this->assertSame('<p>Programació</p>', $fake->Body);
        $this->assertStringContainsString('Cancel·lat', $fake->AltBody);
    }

    public function test_phpmailer_errors_become_transport_exceptions(): void
    {
        $transport = new PhpMailerTransport($this->config(), fn () => $this->fakeMailer(fail: true));

        $this->expectException(TransportException::class);

        $transport->send((new Email)->from('club@example.org')->to('gestor@example.org')->subject('Prova')->text('Prova'));
    }
}
