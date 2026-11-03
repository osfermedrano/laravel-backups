<?php

namespace Osfermedrano\Backups\Support;

/**
 * ¿Vale la pena intentar mandar un mail? Mailers de prueba (log, array) o SMTP sin host = no.
 */
class MailConfigurado
{
    public static function ok(): bool
    {
        $mailer = config('mail.default');

        if (in_array($mailer, [null, '', 'log', 'array'], true)) {
            return false;
        }

        if (config("mail.mailers.{$mailer}.transport") === 'smtp' || $mailer === 'smtp') {
            return filled(config("mail.mailers.{$mailer}.host"));
        }

        return true;
    }
}
