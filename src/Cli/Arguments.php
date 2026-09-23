<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Cli;

/**
 * Parser minimo de argumentos: posicionales, --clave=valor y --flag. Sin dependencias.
 */
final class Arguments
{
    /** @var list<string> */
    public readonly array $positional;

    /** @var array<string, string|true> */
    public readonly array $options;

    /** @param list<string> $argv Sin el nombre del script. */
    public function __construct(array $argv)
    {
        $positional = [];
        $options = [];

        foreach ($argv as $argument) {
            if (str_starts_with($argument, '--')) {
                $body = substr($argument, 2);
                $eq = strpos($body, '=');
                if ($eq === false) {
                    $options[$body] = true;
                } else {
                    $options[substr($body, 0, $eq)] = substr($body, $eq + 1);
                }

                continue;
            }

            $positional[] = $argument;
        }

        $this->positional = $positional;
        $this->options = $options;
    }

    public function value(string $name, ?string $default = null): ?string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function flag(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function argument(int $index): ?string
    {
        return $this->positional[$index] ?? null;
    }
}
