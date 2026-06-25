<?php

namespace App\Modules\Translator\Jobs;

use App\Modules\Translator\Models\TranslatorAssignment;
use App\Modules\Translator\Services\TranslatorAiAssistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunTranslatorAiAssistJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $assignmentId,
    ) {
        $this->onConnection('redis');
        $this->onQueue('translator-ai');
    }

    public function uniqueId(): string
    {
        return 'assignment:'.$this->assignmentId;
    }

    public function handle(TranslatorAiAssistService $service): void
    {
        $assignment = TranslatorAssignment::query()->find($this->assignmentId);
        if ($assignment === null) {
            return;
        }

        $service->requestAi($assignment);
    }
}
