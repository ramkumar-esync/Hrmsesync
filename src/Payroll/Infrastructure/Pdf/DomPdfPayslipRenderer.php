<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\Service\PayslipRenderer;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Log;

/**
 * Renders an issued payslip to PDF and stores it on the private disk.
 *
 * Nothing here ever writes to a publicly served directory. Payslips are only
 * reachable through the download endpoint, which checks authorisation first.
 */
final class DomPdfPayslipRenderer implements PayslipRenderer
{
    /** Logos above this size bloat every payslip; warn so it gets noticed. */
    private const LOGO_SIZE_WARNING_BYTES = 512_000;

    private ?string $cachedLogo = null;

    private bool $logoResolved = false;

    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly string $disk,
        private readonly string $directory,
        private readonly string $companyName,
        private readonly ?string $companyRegistrationNumber,
        private readonly ?string $companyAddress,
        private readonly ?string $logoPath = null,
        private readonly float $logoHeightMm = 12.0,
    ) {}

    public function render(Payslip $payslip): string
    {
        $pdf = Pdf::loadView('payroll::payslip', [
            'payslip' => $payslip,
            'employee' => $payslip->employee(),
            'netInWords' => AmountInWords::for($payslip->netPay()),
            'company' => [
                'name' => $this->companyName,
                'registration_number' => $this->companyRegistrationNumber,
                'address' => $this->companyAddress,
                'logo' => $this->logo(),
                'logo_height_mm' => $this->logoHeightMm,
            ],
        ])->setPaper('a4');

        $path = sprintf(
            '%s/%s/%s-%s.pdf',
            trim($this->directory, '/'),
            $payslip->period,
            $payslip->employee()->employeeNumber,
            substr($payslip->id->value, 0, 8),
        );

        $this->filesystem->disk($this->disk)->put($path, $pdf->output(), 'private');

        return $path;
    }

    /**
     * The logo as a base64 data URI, or null if none is configured or readable.
     *
     * It is embedded rather than linked because DomPDF resolves references at
     * render time: a URL would mean an HTTP call per payslip, and a bare file
     * path breaks the moment the working directory differs between the web
     * process and the queue worker that actually renders these.
     *
     * A missing logo is never fatal. A payslip is a statutory record, and
     * issuing one without branding beats failing a payroll run over a
     * decorative asset.
     */
    private function logo(): ?string
    {
        if ($this->logoResolved) {
            return $this->cachedLogo;
        }

        $this->logoResolved = true;

        if ($this->logoPath === null || trim($this->logoPath) === '') {
            return null;
        }

        $resolved = $this->resolvePath(trim($this->logoPath));

        if ($resolved === null) {
            Log::warning('Payslip logo is configured but cannot be read; issuing payslips without it.', [
                'configured' => $this->logoPath,
                'tried' => $this->candidatePaths(trim($this->logoPath)),
                'hint' => 'Use a path relative to the project root, or a full absolute path.',
            ]);

            return null;
        }

        // getimagesize both proves this really is an image and reports the MIME
        // type, so a mislabelled file cannot corrupt the document.
        $details = @getimagesize($resolved);

        if ($details === false || ! isset($details['mime'])) {
            Log::warning('Payslip logo is not a readable image file.', ['path' => $resolved]);

            return null;
        }

        if (! in_array($details['mime'], ['image/png', 'image/jpeg', 'image/gif'], true)) {
            Log::warning('Payslip logo must be PNG, JPEG or GIF — DomPDF cannot render this format reliably.', [
                'path' => $resolved,
                'mime' => $details['mime'],
            ]);

            return null;
        }

        $bytes = (int) filesize($resolved);

        if ($bytes > self::LOGO_SIZE_WARNING_BYTES) {
            Log::warning('Payslip logo is large and gets embedded into every payslip.', [
                'path' => $resolved,
                'kilobytes' => (int) round($bytes / 1024),
            ]);
        }

        $contents = file_get_contents($resolved);

        if ($contents === false) {
            return null;
        }

        return $this->cachedLogo = 'data:'.$details['mime'].';base64,'.base64_encode($contents);
    }

    /**
     * Accepts either a full absolute path or one relative to the project root.
     *
     * "/storage/app/private/logo.png" is the natural thing to write and means
     * the project's storage directory to a person, but the filesystem root to
     * PHP. Rather than make that a support question, try both.
     */
    private function resolvePath(string $path): ?string
    {
        foreach ($this->candidatePaths($path) as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function candidatePaths(string $path): array
    {
        $candidates = [$path];

        // Windows absolute paths (C:\...) are left alone.
        if (! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $candidates[] = base_path(ltrim($path, '/'));
        }

        return array_values(array_unique($candidates));
    }
}
