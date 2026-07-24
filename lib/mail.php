<?php
declare(strict_types=1);

/**
 * Mail wrapper. Transport chosen by MAIL_TRANSPORT in config:
 *   'smtp' → PHPMailer over SMTP (SMTP_* config)
 *   'log'  → write a .eml file to storage/mail/ (dev; sends nothing)
 *   'mail' → PHP mail() (cPanel default; the fallback)
 *
 * send_mail() NEVER throws — returns bool and logs on failure. Callers save
 * to the DB first, then attempt mail; a mail failure must not lose the lead.
 */

require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * @param string $to        Recipient email
 * @param string $subject   Subject
 * @param string $htmlBody  HTML body
 * @param string $textBody  Plain-text alternative (optional)
 */
/**
 * @param string       $to        Recipient email
 * @param string       $subject   Subject
 * @param string       $htmlBody  HTML body
 * @param string       $textBody  Plain-text alternative (optional)
 * @param string|array $cc        CC — array of emails or comma/;-separated string
 * @param string|array $bcc       BCC — array of emails or comma/;-separated string
 * @param array|null  &$meta      Filled with ['message_id'=>…, 'error'=>…] for logging
 * @param string       $replyTo   Optional Reply-To address (blank = none)
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody = '', $cc = [], $bcc = [], ?array &$meta = null, string $replyTo = ''): bool
{
    $transport = defined('MAIL_TRANSPORT') ? (string)MAIL_TRANSPORT : 'mail';
    $fromAddr  = defined('MAIL_FROM')      ? (string)MAIL_FROM      : ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName  = defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'All The Venues';

    $ccList  = _mail_addresses($cc);
    $bccList = _mail_addresses($bcc);
    $replyTo = (trim($replyTo) !== '' && filter_var(trim($replyTo), FILTER_VALIDATE_EMAIL)) ? trim($replyTo) : '';
    $meta    = ['message_id' => '', 'error' => ''];

    if ($textBody === '') {
        $textBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)) ?? '');
    }

    try {
        if ($transport === 'log') {
            return _mail_log($to, $fromAddr, $fromName, $subject, $htmlBody, $ccList, $bccList, $replyTo);
        }

        require_once __DIR__ . '/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        if ($transport === 'smtp') {
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USER') ? (string)SMTP_USER : '';
            $mail->Password   = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
            $secure = defined('SMTP_SECURE') ? (string)SMTP_SECURE : 'tls';
            if ($secure !== '') {
                $mail->SMTPSecure = $secure;   // 'tls' | 'ssl'
            }
        } else {
            $mail->isMail();   // PHP mail()
        }

        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($to);
        if ($replyTo !== '') { $mail->addReplyTo($replyTo); }
        foreach ($ccList as $addr)  { $mail->addCC($addr); }
        foreach ($bccList as $addr) { $mail->addBCC($addr); }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
        $meta['message_id'] = (string)$mail->getLastMessageID();
        return true;
    } catch (Throwable $e) {
        $meta['error'] = $e->getMessage();
        error_log('send_mail failed to ' . $to . ': ' . $e->getMessage());
        return false;
    }
}

/** Normalize a cc/bcc value (array or comma/;-separated string) → distinct valid emails. */
function _mail_addresses($value): array
{
    if (is_string($value)) {
        $value = preg_split('/[,;]+/', $value) ?: [];
    }
    $out = [];
    foreach ((array)$value as $addr) {
        $addr = trim((string)$addr);
        if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL) && !in_array($addr, $out, true)) {
            $out[] = $addr;
        }
    }
    return $out;
}

/** Dev transport: write the message to storage/mail/ instead of sending. */
function _mail_log(string $to, string $from, string $fromName, string $subject, string $htmlBody, array $cc = [], array $bcc = [], string $replyTo = ''): bool
{
    $dir = dirname(__DIR__) . '/storage/mail';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $to . '-' . $subject);
    $file = $dir . '/' . date('Ymd-His') . '-' . substr((string)$safe, 0, 60) . '-' . substr(sha1($to . $subject . microtime()), 0, 6) . '.eml';
    $eml  = "From: $fromName <$from>\n"
          . "To: $to\n"
          . ($replyTo !== '' ? "Reply-To: $replyTo\n" : '')
          . ($cc  ? 'Cc: '  . implode(', ', $cc)  . "\n" : '')
          . ($bcc ? 'Bcc: ' . implode(', ', $bcc) . "\n" : '')
          . "Subject: $subject\n"
          . "Content-Type: text/html; charset=UTF-8\n\n"
          . $htmlBody . "\n";
    if (@file_put_contents($file, $eml) === false) {
        error_log('_mail_log: cannot write ' . $file);
        return false;
    }
    return true;
}
