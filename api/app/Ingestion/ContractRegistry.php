<?php

namespace App\Ingestion;

use App\Ingestion\Contracts\ParserContract;
use InvalidArgumentException;

class ContractRegistry
{
    /**
     * @return array<int, ParserContract>
     */
    public function all(): array
    {
        return collect(config('mtrack.ingestion.contracts', []))
            ->keys()
            ->map(fn (string $contractKey): ParserContract => $this->resolve($contractKey))
            ->values()
            ->all();
    }

    public function resolve(string $contractKey): ParserContract
    {
        $contractClass = config("mtrack.ingestion.contracts.{$contractKey}");

        if (! is_string($contractClass) || ! class_exists($contractClass)) {
            throw new InvalidArgumentException("Parser contract [{$contractKey}] is not registered.");
        }

        $contract = app($contractClass);

        if (! $contract instanceof ParserContract) {
            throw new InvalidArgumentException("Parser contract [{$contractKey}] must implement ParserContract.");
        }

        if ($contract->key() !== $contractKey) {
            throw new InvalidArgumentException("Parser contract [{$contractKey}] returned mismatched key [{$contract->key()}].");
        }

        return $contract;
    }
}
