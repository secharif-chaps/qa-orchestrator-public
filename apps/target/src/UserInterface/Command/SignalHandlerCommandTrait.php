<?php

namespace App\UserInterface\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

trait SignalHandlerCommandTrait
{
    private function setupSignalHandlers(SymfonyStyle $io, ?callable $onSignal = null): void
    {
        if (!\extension_loaded('pcntl')) {
            $io->warning('pcntl extension not loaded, signal handling disabled');

            return;
        }

        $signalHandler = function (int $signal) use ($io, $onSignal): void {
            $signalName = match ($signal) {
                \SIGTERM => 'SIGTERM',
                \SIGINT => 'SIGINT (Ctrl+C)',
                default => "Signal $signal",
            };

            $io->note("$signalName received, shutting down gracefully...");

            if (null !== $onSignal) {
                $onSignal($signal);
            }

            $this->shouldStop = true;
        };

        pcntl_signal(\SIGTERM, $signalHandler);
        pcntl_signal(\SIGINT, $signalHandler);

        // Enable signal handling
        pcntl_async_signals(true);
    }
}
