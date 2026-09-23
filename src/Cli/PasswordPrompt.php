<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Cli;

/**
 * Pide la contraseña sin eco. Si no hay terminal, devuelve null y que el llamador decida.
 */
final class PasswordPrompt
{
    public static function ask(string $prompt = 'Contraseña del certificado: '): ?string
    {
        if (! self::hasTty()) {
            return null;
        }

        fwrite(STDERR, $prompt);
        $previous = shell_exec('stty -g 2>/dev/null');
        $canHide = is_string($previous) && trim($previous) !== '';

        if ($canHide) {
            shell_exec('stty -echo 2>/dev/null');
        }

        try {
            $line = fgets(STDIN);
        } finally {
            if ($canHide) {
                shell_exec('stty ' . trim((string) $previous) . ' 2>/dev/null');
            }
            fwrite(STDERR, PHP_EOL);
        }

        return $line === false ? null : rtrim($line, "\r\n");
    }

    private static function hasTty(): bool
    {
        return defined('STDIN') && function_exists('posix_isatty') ? @posix_isatty(STDIN) : (defined('STDIN') && stream_isatty(STDIN));
    }
}
