<?php

declare(strict_types=1);

namespace BEAR\Async\Exception;

/**
 * Exception thrown when a task/request could not be dispatched to a worker Runtime
 *
 * ext-parallel's Runtime::run() returns null instead of a Future when the
 * task could not be scheduled (e.g. the Runtime is closed). When this
 * happens the task's result would otherwise be silently left unset, so this
 * exception surfaces the failure explicitly, identifying the request by URI.
 */
final class TaskNotDispatchedException extends RuntimeException
{
}
