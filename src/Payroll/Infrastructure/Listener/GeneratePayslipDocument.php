<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Listener;

use HR\Payroll\Domain\Event\PayslipIssued;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\Service\PayslipRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PDF generation is queued: a 500-person payroll run must not block the HTTP
 * request that finalised it. The payslip is already issued and correct in the
 * database; the document is a derived artefact that can be regenerated.
 */
final readonly class GeneratePayslipDocument implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private PayslipRepository $payslips,
        private PayslipRenderer $renderer,
    ) {
        $this->queue = 'documents';
    }

    public function handle(PayslipIssued $event): void
    {
        $payslip = $this->payslips->find($event->payslipId);

        if ($payslip === null) {
            Log::warning('Payslip vanished before its document could be generated.', [
                'payslip_id' => $event->payslipId->value,
            ]);

            return;
        }

        $payslip->attachDocument($this->renderer->render($payslip));

        $this->payslips->save($payslip);
    }
}
