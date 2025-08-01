<?php

namespace Refynd\Events;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Listener
{
    public function __construct(
        public string $event,
        public int $priority = 0
    ) {}
}
