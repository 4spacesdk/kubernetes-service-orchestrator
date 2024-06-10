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
     * @throws \Exception
     */
    public function send(string $subject, string $body, string $receiverName, string $receiverEmail): bool {
        if (!self::IsConfigured()) {
            throw new \Exception('Email host is not configured');
        }

        $config = new Email();
        $config->SMTPHost = getenv('EMAIL_SERVICE_HOST');
        $config->SMTPPort = getenv('EMAIL_SERVICE_PORT');
        if ($config->SMTPPort) {
            $config->SMTPCrypto = '';
        }
        $config->SMTPUser = getenv('EMAIL_SERVICE_USER');
        $config->SMTPPass = getenv('EMAIL_SERVICE_PASS');

        $email = \CodeIgniter\Config\Services::email($config);
        $email
            ->setFrom(getenv('EMAIL_SERVICE_SENDER'), '4 Spaces KSO')
            ->setTo([$receiverName => $receiverEmail])
            ->setSubject($subject)
            ->setMessage(view('Email/Template', [
                'receiverName' => $receiverName,
                'subject' => $subject,
                'message' => $body,
            ]));

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
