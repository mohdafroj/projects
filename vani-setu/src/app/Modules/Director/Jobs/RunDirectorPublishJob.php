<?php

namespace App\Modules\Director\Jobs;

use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Director\Models\DirectorPublishJob;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RunDirectorPublishJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $publishJobId)
    {
        $this->onQueue('default');
    }

    public function handle(AuditLogger $audit): void
    {
        /** @var DirectorPublishJob $job */
        $job = DirectorPublishJob::query()->with('window')->findOrFail($this->publishJobId);
        /** @var JsWindow $window */
        $window = $job->window;

        try {
            if ($window->status !== 'approved') {
                throw new \RuntimeException('Window must be approved before publishing.');
            }

            $job->forceFill(['status' => 'crc_running', 'ran_at' => now(), 'last_error' => null])->save();
            $pdfPath = $this->generateCrcPdf($window);
            $job->forceFill(['crc_pdf_path' => $pdfPath, 'status' => 'sansad_pushing'])->save();
            $audit->log('director.crc.generated', $job, ['window_id' => $window->id, 'crc_pdf_path' => $pdfPath]);

            $sansadUrl = sprintf('https://digital-sansad.local/published/%s/%s', $window->window_code, Str::uuid());
            $job->forceFill([
                'sansad_url' => $sansadUrl,
                'status' => 'published',
                'finished_at' => now(),
            ])->save();
            $audit->log('director.sansad.pushed', $job, ['window_id' => $window->id, 'sansad_url' => $sansadUrl]);
        } catch (Throwable $exception) {
            $job->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();
            $audit->log('director.job.failed', $job, ['window_id' => $window->id, 'error' => $exception->getMessage()]);

            throw $exception;
        }
    }

    private function generateCrcPdf(JsWindow $window): string
    {
        $html = $this->crcHtml($window);
        $path = trim((string) env('DIRECTOR_CRC_PATH', 'director/crc'), '/').'/window-'.$window->id.'.pdf';

        if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
            $pdf = \Spatie\Browsershot\Browsershot::html($html)->pdf();
        } else {
            $pdf = $this->minimalPdf(strip_tags($html));
        }

        Storage::disk(env('DIRECTOR_CRC_DISK', 'local'))->put($path, $pdf);

        return $path;
    }

    private function crcHtml(JsWindow $window): string
    {
        $blocks = $window->blocks()->get();
        $rows = $blocks->map(fn ($block) => sprintf(
            '<p><strong>%s-%s</strong> %s</p>',
            e((string) $block->start_ms),
            e((string) $block->end_ms),
            e($block->text),
        ))->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:serif;padding:32px}h1{font-size:20px}p{line-height:1.5}</style></head><body><h1>CRC Window '.$window->window_code.'</h1>'.$rows.'</body></html>';
    }

    private function minimalPdf(string $text): string
    {
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::limit($text, 900, ''));
        $stream = "BT /F1 12 Tf 72 740 Td ({$safe}) Tj ET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length '.strlen($stream).' >> stream'."\n".$stream."\n".'endstream endobj',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer << /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
