<?php

namespace App\Modules\SpeechToSpeech\Providers;

use App\Modules\SpeechToSpeech\Commands\BackfillDegradedStatusCommand;
use App\Modules\SpeechToSpeech\Commands\QaSummaryCommand;
use App\Modules\SpeechToSpeech\Commands\PruneAudioArchiveCommand;
use App\Modules\SpeechToSpeech\Commands\RecheckCommand;
use App\Modules\SpeechToSpeech\Commands\RecheckRetryFailedCommand;
use App\Modules\SpeechToSpeech\Services\Recheck\FailedRecheckRetryService;
use App\Modules\SpeechToSpeech\Services\Recheck\InternalAudioUrlSigner;
use App\Modules\SpeechToSpeech\Services\Recheck\MlGatewayAsrTranscriber;
use App\Modules\SpeechToSpeech\Services\Recheck\NullSecondPassTranscriber;
use App\Modules\SpeechToSpeech\Services\Recheck\SecondPassTranscriber;
use App\Modules\SpeechToSpeech\Services\Recheck\TranscriptDriftAnalyzer;
use Illuminate\Support\ServiceProvider;

class SpeechToSpeechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InternalAudioUrlSigner::class, function () {
            return new InternalAudioUrlSigner(
                key: (string) config('app.key'),
                baseUrl: (string) config('services.s2s_recheck.internal_base_url', 'http://vani-setu-web'),
            );
        });

        $this->app->bind(SecondPassTranscriber::class, function ($app) {
            $driver = (string) config('services.s2s_recheck.transcriber', 'null');
            return match ($driver) {
                'ml_gateway' => new MlGatewayAsrTranscriber(
                    signer: $app->make(InternalAudioUrlSigner::class),
                    gatewayBaseUrl: (string) config('services.s2s_recheck.gateway_base_url', 'http://vani-setu-ml-gateway:8000'),
                    serviceToken: (string) config('services.ml_gateway.service_token', ''),
                    timeoutSeconds: (float) config('services.s2s_recheck.timeout', 25.0),
                    urlTtlSeconds: (int) config('services.s2s_recheck.url_ttl', 300),
                ),
                default => new NullSecondPassTranscriber(),
            };
        });

        $this->app->singleton(TranscriptDriftAnalyzer::class, function () {
            return new TranscriptDriftAnalyzer(
                driftThreshold: (float) config('services.s2s_recheck.drift_threshold', 0.15),
                correctionConfidence: (float) config('services.s2s_recheck.correction_confidence', 0.85),
            );
        });

        $this->app->singleton(FailedRecheckRetryService::class, function () {
            return new FailedRecheckRetryService(
                maxAttempts: (int) config('services.s2s_recheck.retry_max_attempts', 3),
                coolDownSeconds: (int) config('services.s2s_recheck.retry_cool_down_seconds', 300),
                batchSize: (int) config('services.s2s_recheck.retry_batch_size', 50),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RecheckCommand::class,
                QaSummaryCommand::class,
                PruneAudioArchiveCommand::class,
                BackfillDegradedStatusCommand::class,
                RecheckRetryFailedCommand::class,
            ]);
        }
    }
}
