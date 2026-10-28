<?php
declare(strict_types=1);

namespace Tms;

/** An error whose message is safe to show to the visitor. */
final class UserError extends \RuntimeException
{
    public function __construct(string $message, private int $status = 400)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
