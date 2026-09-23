<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Certificate;

/**
 * Rutas PEM listas para cURL. Si son temporales, release() las borra y elimina el
 * directorio; el destructor lo intenta tambien por si a alguien se le olvida el finally.
 */
final class PemCredential
{
    private bool $released = false;

    /**
     * @param string|null $privateKeyPassphrase Frase con la que se cifro la clave temporal, si se cifro.
     * @param string|null $temporaryDirectory Directorio a borrar en release(); null si los ficheros son del usuario.
     */
    public function __construct(
        public readonly string $certificatePath,
        public readonly string $privateKeyPath,
        #[\SensitiveParameter]
        public readonly ?string $privateKeyPassphrase = null,
        private readonly ?string $temporaryDirectory = null,
    ) {}

    public function isTemporary(): bool
    {
        return $this->temporaryDirectory !== null;
    }

    public function release(): void
    {
        if ($this->released || $this->temporaryDirectory === null) {
            $this->released = true;

            return;
        }

        $this->released = true;

        foreach ([$this->privateKeyPath, $this->certificatePath] as $path) {
            if (is_file($path)) {
                // Sobrescribir antes de borrar; en disco normal no garantiza nada, pero no cuesta.
                $size = filesize($path);
                if ($size !== false && $size > 0) {
                    @file_put_contents($path, str_repeat("\0", $size));
                }
                @unlink($path);
            }
        }

        if (is_dir($this->temporaryDirectory)) {
            @rmdir($this->temporaryDirectory);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
