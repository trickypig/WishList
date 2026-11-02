<?php

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends email over SMTP using the SMTP_* and MAIL_FROM settings in .env.
 * Without SMTP_HOST, development writes the email to data/mail.log instead of sending it.
 */
class Mailer
{
    /** @throws Throwable if the email could not be sent */
    public static function send(string $to, string $subject, string $text, string $html): void
    {
        $host = env('SMTP_HOST');

        if ($host === '') {
            if (getAppConfig()['app_env'] === 'production') {
                throw new RuntimeException('SMTP_HOST is not configured');
            }
            self::writeToLog($to, $subject, $text);
            return;
        }

        require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
        require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

        $port = (int) env('SMTP_PORT', '465');

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 10;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(env('MAIL_FROM', 'noreply@trickypig.com'), 'Wish List');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
    }

    private static function writeToLog(string $to, string $subject, string $text): void
    {
        $dir = __DIR__ . '/../../data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $entry = '[' . date('Y-m-d H:i:s') . "] To: {$to}\nSubject: {$subject}\n\n{$text}\n\n";
        file_put_contents($dir . '/mail.log', $entry, FILE_APPEND);
    }
}
