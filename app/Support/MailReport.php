<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

final class MailReport
{
    public static function failure(Throwable $error): string
    {
        $text = str_replace(["\r", "\n"], ' ', $error->getMessage());
        $secret = (string) config('mail.mailers.smtp.password');

        if ($secret !== '') {
            $text = str_replace($secret, '***', $text);
        }

        $text = preg_replace('#://[^/\s]*@#', '://***@', $text) ?? $text;
        $text = trim($text);

        if (strlen($text) > 160) {
            $text = substr($text, 0, 160);
        }

        return 'mail_error='.$error::class.($text !== '' ? ' '.$text : '');
    }

    public static function smtpReply(string $debug): string
    {
        $secret = (string) config('mail.mailers.smtp.password');

        if ($secret !== '') {
            $debug = str_replace($secret, '***', $debug);
        }

        $reply = '';

        foreach (preg_split("/\R/", $debug) ?: [] as $line) {
            if (preg_match('/\] < ([245]\d{2}[ -].{0,140})$/', $line, $match) !== 1) {
                continue;
            }

            $text = $match[1];

            if (preg_match('/AUTH|PASS|LOGIN|PLAIN|CRAM|XOAUTH|secret|@/i', $text) === 1) {
                continue;
            }

            if (preg_match('/^[245]\d{2} /', $text) === 1) {
                $reply = $text;
            }
        }

        return $reply;
    }

    public static function debugFrom(Throwable $error): string
    {
        if ($error instanceof TransportExceptionInterface) {
            return $error->getDebug();
        }

        return '';
    }
}
