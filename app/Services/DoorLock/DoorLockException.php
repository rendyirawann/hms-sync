<?php

namespace App\Services\DoorLock;

use RuntimeException;

/** The lock vendor rejected or could not be reached for a code change. */
class DoorLockException extends RuntimeException
{
}
