<?php

namespace app\services\EinSkipTracing;

/** A `manual_companies` row whose `data` cannot be mapped at all (not a JSON object). The sync logs it and moves past it. */
class InvalidSourceRowException extends \RuntimeException
{
}
