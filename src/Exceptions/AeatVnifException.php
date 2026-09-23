<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Exceptions;

use Throwable;

/**
 * Todas las excepciones del paquete implementan esto: basta un
 * `catch (AeatVnifException $e)` sin conocer las clases concretas.
 */
interface AeatVnifException extends Throwable {}
