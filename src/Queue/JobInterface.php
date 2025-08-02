<?php

namespace Refynd\Queue;

interface JobInterface
{
    public function handle(): void;
    public function getName(): string;
    public function getPayload(): array;
    public function failed(\Exception $exception): void;
}
