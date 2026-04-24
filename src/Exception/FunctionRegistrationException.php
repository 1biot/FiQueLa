<?php

namespace FQL\Exception;

/**
 * Thrown by {@see \FQL\Functions\FunctionRegistry} during
 * `register()`/`override()`/`unregister()`/`loadConfig()` when the requested
 * operation is invalid: duplicate name, missing class, class not implementing
 * one of the function contracts, unknown function on unregister, or malformed
 * config file.
 */
class FunctionRegistrationException extends InvalidArgumentException
{
}
