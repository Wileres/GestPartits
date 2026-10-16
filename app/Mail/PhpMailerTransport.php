<?php

namespace App\Mail;

use Closure;
use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transport de correu de Laravel que envia per SMTP amb PHPMailer.
 * Es registra com a `phpmailer` a AppServiceProvider.
 */
class PhpMailerTransport extends AbstractTransport
{
    /** @var Closure(): PHPMailer */
    private readonly Closure $mailerFactory;

    /**
     * @param  array{host?: string, port?: int, encryption?: string|null, username?: string|null, password?: string|null, timeout?: int, helo?: string|null}  $config
     * @param  (Closure(): PHPMailer)|null  $mailerFactory  Permet substituir PHPMailer a les proves.
     */
    public function __construct(private readonly array $config, ?Closure $mailerFactory = null)
    {
        parent::__construct();

        $this->mailerFactory = $mailerFactory ?? fn (): PHPMailer => new PHPMailer(true);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $mailer = $this->configure(($this->mailerFactory)());

        try {
            $this->fill($mailer, $email, $message);
            $mailer->send();
        } catch (PhpMailerException $exception) {
            // El missatge de PHPMailer no inclou credencials.
            throw new TransportException('No s\'ha pogut enviar el correu: '.$exception->getMessage(), 0, $exception);
        }
    }

    public function configure(PHPMailer $mailer): PHPMailer
    {
        $mailer->isSMTP();
        $mailer->Host = (string) ($this->config['host'] ?? '127.0.0.1');
        $mailer->Port = (int) ($this->config['port'] ?? 587);
        $mailer->Timeout = (int) ($this->config['timeout'] ?? 15);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Encoding = PHPMailer::ENCODING_BASE64;
        $mailer->SMTPDebug = 0;

        $encryption = strtolower((string) ($this->config['encryption'] ?? ''));
        $mailer->SMTPSecure = match ($encryption) {
            'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
            'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mailer->SMTPAutoTLS = $mailer->SMTPSecure !== '';

        $username = (string) ($this->config['username'] ?? '');
        $mailer->SMTPAuth = $username !== '';
        $mailer->Username = $username;
        $mailer->Password = (string) ($this->config['password'] ?? '');

        if (! empty($this->config['helo'])) {
            $mailer->Helo = (string) $this->config['helo'];
        }

        return $mailer;
    }

    private function fill(PHPMailer $mailer, Email $email, SentMessage $message): void
    {
        $from = $email->getFrom()[0] ?? $message->getEnvelope()->getSender();
        $mailer->setFrom($from->getAddress(), $from->getName(), false);
        $mailer->Sender = $message->getEnvelope()->getSender()->getAddress();

        foreach ($email->getReplyTo() as $address) {
            $mailer->addReplyTo($address->getAddress(), $address->getName());
        }

        // Els destinataris efectius són els del sobre (inclouen cc i bcc).
        $visible = array_map(fn (Address $a): string => strtolower($a->getAddress()), [...$email->getTo(), ...$email->getCc()]);

        foreach ($email->getTo() as $address) {
            $mailer->addAddress($address->getAddress(), $address->getName());
        }

        foreach ($email->getCc() as $address) {
            $mailer->addCC($address->getAddress(), $address->getName());
        }

        foreach ($message->getEnvelope()->getRecipients() as $address) {
            if (! in_array(strtolower($address->getAddress()), $visible, true)) {
                $mailer->addBCC($address->getAddress(), $address->getName());
            }
        }

        $mailer->Subject = (string) $email->getSubject();

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        if ($html !== null) {
            $mailer->isHTML(true);
            $mailer->Body = is_resource($html) ? (string) stream_get_contents($html) : (string) $html;
            $mailer->AltBody = $text === null ? '' : (is_resource($text) ? (string) stream_get_contents($text) : (string) $text);
        } else {
            $mailer->isHTML(false);
            $mailer->Body = is_resource($text) ? (string) stream_get_contents($text) : (string) $text;
        }

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'adjunt';
            $mailer->addStringAttachment($attachment->getBody(), $filename, PHPMailer::ENCODING_BASE64, $attachment->getMediaType().'/'.$attachment->getMediaSubtype());
        }
    }

    public function __toString(): string
    {
        return sprintf('phpmailer://%s:%d', $this->config['host'] ?? '127.0.0.1', (int) ($this->config['port'] ?? 587));
    }
}
