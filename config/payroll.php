<?php

return [
    'currency' => env('PAYROLL_CURRENCY', 'MYR'),

    /*
     * Divisor used to turn a monthly salary into a daily rate for unpaid-leave
     * deductions. A fixed divisor means the same absence costs the same in
     * February as in March. Some organisations use calendar days instead —
     * check your employment contracts and handbook before changing this.
     */
    'working_days_per_month' => (int) env('PAYROLL_WORKING_DAYS_PER_MONTH', 22),

    'payslips' => [
        // Must be a private disk. Payslips are never publicly addressable.
        'disk' => env('PAYSLIP_DISK', 'local'),
        'path' => env('PAYSLIP_PATH', 'payslips'),
    ],

    /*
     * Which statutory engine to use: 'malaysia' or 'none'.
     * See config/statutory/malaysia.php.
     */
    'statutory_profile' => env('STATUTORY_PROFILE', 'malaysia'),

    /*
     * Monthly tax withholding engine.
     *   manual              — HR enters PCB from their own LHDN calculator (default)
     *   progressive_estimate — rough estimate, for non-production use only
     */
    'income_tax_engine' => env('PCB_ENGINE', 'manual'),

    'company' => [
        'name' => env('COMPANY_NAME', env('APP_NAME', 'Your Company Sdn Bhd')),
        'registration_number' => env('COMPANY_REGISTRATION_NUMBER'),
        'address' => env('COMPANY_ADDRESS'),

        /*
         * Logo printed on the payslip: a PNG or JPEG on this server, given
         * either as a path relative to the project root or as a full absolute
         * path. It is embedded into the PDF rather than linked, so the file
         * must be readable by PHP at render time — including by the queue
         * worker, which is what actually renders payslips.
         *
         * Use a raster format. DomPDF's SVG support is partial and fails
         * quietly on anything with gradients, masks or embedded fonts.
         * Around 600px wide is plenty; the whole file is base64-encoded into
         * every payslip, so a 2 MB logo makes 2 MB of overhead per document.
         */
        'logo_path' => env('COMPANY_LOGO_PATH'),

        // Printed height in millimetres. Width scales to keep the aspect ratio.
        'logo_height_mm' => (float) env('COMPANY_LOGO_HEIGHT_MM', 12),
    ],
];
