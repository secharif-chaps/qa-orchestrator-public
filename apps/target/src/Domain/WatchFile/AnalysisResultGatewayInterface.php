<?php

namespace App\Domain\WatchFile;

interface AnalysisResultGatewayInterface
{
    public function getLastOrCreate(WatchFile $watchFile): AnalysisResult;

    public function save(AnalysisResult $analysisResult): void;
}
