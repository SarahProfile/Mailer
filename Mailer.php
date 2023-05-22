<?php

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'sendgrid-php/sendgrid-php.php';
require_once 'mailgun-php/vendor/autoload.php';

class Mailer
{
    private string $from;
    private array $to = [];
    private array $replyTo = [];
    private array $cc = [];
    private array $bcc = [];
    private array $attachments = [];
    private string $html = '';
    private string $text = '';
    private bool $useHtml = false;
    private bool $addAltBody = false;
    private bool $useSMTP = false;
    private string $smtpHost = '';
    private int $smtpPort = 587;
    private string $smtpUsername = '';
    private string $smtpPassword = '';
    private string $selectedAPI = 'PHPMailer';

    private array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'
    ];

    public function setFrom(string $email, string $name): void
    {
        $this->from = $this->formatRecipient($email, $name);
    }

    public function addTo(string $email, string $name = ''): void
    {
        $this->to[] = $this->formatRecipient($email, $name);
    }

    public function addReplyTo(string $email, string $name = ''): void
    {
        $this->replyTo[] = $this->formatRecipient($email, $name);
    }

    public function addCc(string $email, string $name = ''): void
    {
        $this->cc[] = $this->formatRecipient($email, $name);
    }

    public function addBcc(string $email, string $name = ''): void
    {
        $this->bcc[] = $this->formatRecipient($email, $name);
    }

    public function setHTML(string $html): void
    {
        $this->html = $html;
        $this->useHtml = true;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
        $this->useHtml = false;
    }

    public function setAltBody(bool $addAltBody): void
    {
        $this->addAltBody = $addAltBody;
    }

    public function addAttachment(string $filePath): void
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extension, $this->allowedExtensions)) {
            $this->attachments[] = $filePath;
        }
    }

    public function useSMTP(bool $useSMTP): void
    {
        $this->useSMTP = $useSMTP;
    }

    public function setSMTPConfig(string $host, int $port, string $username, string $password): void
    {
        $this->smtpHost = $host;
        $this->smtpPort = $port;
        $this->smtpUsername = $username;
        $this->smtpPassword = $password;
    }

    public function setAPI(string $api): void
    {
        $this->selectedAPI = $api;
    }

    public function send(): bool
    {
        switch ($this->selectedAPI) {
            case 'PHPMailer':
                return $this->sendWithPHPMailer();
            case 'SendGrid':
                return $this->sendWithSendGrid();
            case 'Mailgun':
                return $this->sendWithMailgun();
            default:
                return false;
        }
    }

    private function sendWithPHPMailer(): bool
    {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer();
            $mailer->isSMTP($this->useSMTP);
            $mailer->Host = $this->smtpHost;
            $mailer->Port = $this->smtpPort;
            $mailer->SMTPAuth = $this->useSMTP;
            $mailer->Username = $this->smtpUsername;
            $mailer->Password = $this->smtpPassword;
            $mailer->setFrom($this->from['email'], $this->from['name']);
            $mailer->addReplyTo($this->replyTo);
            $mailer->addAddress($this->to);
            $mailer->addCC($this->cc);
            $mailer->addBCC($this->bcc);
            $mailer->isHTML($this->useHtml);
            $mailer->Subject = '';
            $mailer->Body = $this->html;
            $mailer->AltBody = $this->addAltBody ? $this->text : '';

            foreach ($this->attachments as $attachment) {
                $mailer->addAttachment($attachment);
            }

            return $mailer->send();
        } catch (Exception $e) {
            return false;
        }
    }

    private function sendWithSendGrid(): bool
    {
        try {
            $email = new \SendGrid\Mail\Mail();
            $email->setFrom($this->from['email'], $this->from['name']);
            $email->setReplyTo($this->replyTo);
            $email->addTo($this->to);
            $email->addCc($this->cc);
            $email->addBcc($this->bcc);
            $email->setSubject('');
            $email->addContent('text/html', $this->html);
            $email->addContent('text/plain', $this->addAltBody ? $this->text : '');

            foreach ($this->attachments as $attachment) {
                $attachmentContent = base64_encode(file_get_contents($attachment));
                $email->addAttachment(
                    $attachmentContent,
                    mime_content_type($attachment),
                    pathinfo($attachment, PATHINFO_BASENAME)
                );
            }

            $sendgrid = new \SendGrid($this->smtpUsername);
            $response = $sendgrid->send($email);

            return $response->statusCode() === 202;
        } catch (Exception $e) {
            return false;
        }
    }

    private function sendWithMailgun(): bool
    {
        try {
            $mg = Mailgun\Mailgun::create($this->smtpUsername);
            $params = [
                'from' => $this->from['email'],
                'subject' => '',
                'html' => $this->html,
                'text' => $this->addAltBody ? $this->text : ''
            ];

            foreach ($this->to as $recipient) {
                $params['to'] = $recipient['email'];
                $mg->messages()->send($this->smtpHost, $params);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function formatRecipient(string $email, string $name): array
    {
        return [
            'email' => $email,
            'name' => $name
        ];
    }
}

// Example usage:

$mailer = new Mailer();
$mailer->setFrom('sender@example.com', 'Sender');
$mailer->addTo('recipient1@example.com', 'Recipient 1');
$mailer->addTo('recipient2@example.com', 'Recipient 2');
$mailer->addCc('cc@example.com');
$mailer->addBcc('bcc@example.com');
$mailer->addReplyTo('replyto@example.com');
$mailer->setHTML('<html><body><h1>Hello, World!</h1></body></html>');
$mailer->setText('Hello, World!');
$mailer->setAltBody(true);
$mailer->addAttachment('/path/to/file.pdf');
$mailer->useSMTP(true);
$mailer->setSMTPConfig('smtp.example.com', 587, 'smtp_username', 'smtp_password');
$mailer->setAPI('SendGrid');
$result = $mailer->send();

if ($result) {
    echo 'Email sent successfully.';
} else {
    echo 'Failed to send email.';
}
