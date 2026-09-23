<?php namespace App\Libraries;

use CodeIgniter\CLI\CLI;
use Config\Email;
use Config\Services;

class EmailLib {

    public static function IsConfigured(): bool {
        return
            strlen(getenv('EMAIL_SERVICE_HOST')) > 0
            && strlen(getenv('EMAIL_SERVICE_PORT')) > 0
            && strlen(getenv('EMAIL_SERVICE_USER')) > 0
            && strlen(getenv('EMAIL_SERVICE_PASS')) > 0
            && strlen(getenv('EMAIL_SERVICE_SENDER')) > 0;
    }

    /**
     * Who a mail is from, as the inbox shows it: the installation, PROJECT_NAME, so mails from
     * two installations can be told apart - or KSO when it is not set.
     */
    public static function SenderName(): string {
        $name = trim((string) getenv('PROJECT_NAME'));

        return $name !== '' ? $name : 'KSO';
    }

    /** A subject, after the sender's name: "klartboard | Choose a new password". */
    public static function Subject(string $text): string {
        return self::SenderName() . ' | ' . $text;
    }

    /** KSO's mark, as PNG: mail clients show no SVG. */
    public const LogoPath = FCPATH . 'kso.png';

    /**
     * A mail's HTML: the message in KSO's template, with the installation named at the top and
     * its contact at the bottom - PROJECT_NAME, PROJECT_CONTACT_URL and PROJECT_CONTACT_EMAIL,
     * each left out when it is not set.
     */
    public static function Render(string $subject, string $body, string $receiverName, ?string $logoCid = null): string {
        return view('Email/Template', [
            'receiverName' => $receiverName,
            'subject' => $subject,
            'message' => $body,
            'logoCid' => $logoCid,
            'projectName' => trim((string) getenv('PROJECT_NAME')),
            'contactUrl' => trim((string) getenv('PROJECT_CONTACT_URL')),
            'contactEmail' => trim((string) getenv('PROJECT_CONTACT_EMAIL')),
        ]);
    }

    /**
     * @throws \Exception
     *
     * Not measured: from here down it is an smtp conversation. Whether we are configured to
     * have one at all is decided by `IsConfigured()` above, which is.
     *
     * @codeCoverageIgnore
     */
    public function send(string $subject, string $body, string $receiverName, string $receiverEmail): bool {
        if (!self::IsConfigured()) {
            throw new \Exception('Email host is not configured');
        }

        $config = new Email();
        $config->SMTPHost = getenv('EMAIL_SERVICE_HOST');
        $config->SMTPPort = getenv('EMAIL_SERVICE_PORT');
        if ($config->SMTPPort == 465) {
            $config->SMTPCrypto = '';
        }
        $config->SMTPUser = getenv('EMAIL_SERVICE_USER');
        $config->SMTPPass = getenv('EMAIL_SERVICE_PASS');

        $email = \CodeIgniter\Config\Services::email($config);
        // The logo goes with the mail rather than being fetched: a client that loads no remote
        // images shows it all the same, and so does a mail from an installation it cannot reach.
        $logoCid = null;
        if (is_file(self::LogoPath)) {
            $email->attach(self::LogoPath, 'inline');
            $logoCid = $email->setAttachmentCID(self::LogoPath) ?: null;
        }
        $email
            ->setFrom(getenv('EMAIL_SERVICE_SENDER'), self::SenderName())
            ->setTo([$receiverName => $receiverEmail])
            ->setSubject($subject)
            ->setMessage(self::Render($subject, $body, $receiverName, $logoCid));

        $success = $email->send();

        if (!$success) {
            if (Services::request()->isCLI()) {
                CLI::error('Email not sent');
                CLI::error($email->printDebugger());
            } else {
                \DebugTool\Data::debug("Email not sent", $email->printDebugger());
            }
        }
        return $success;
    }

}
