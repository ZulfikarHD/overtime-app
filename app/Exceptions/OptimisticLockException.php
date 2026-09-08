<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class OptimisticLockException extends HttpException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $message = 'Data pengajuan ini telah diperbarui oleh reviewer lain. Silakan muat ulang data.',
        ?Throwable $previous = null,
        int $code = 0,
        array $headers = []
    ) {
        parent::__construct(409, $message, $previous, $headers, $code);
    }
}
