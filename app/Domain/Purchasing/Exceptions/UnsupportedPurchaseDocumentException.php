<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Exceptions;

use RuntimeException;

final class UnsupportedPurchaseDocumentException extends RuntimeException {}
