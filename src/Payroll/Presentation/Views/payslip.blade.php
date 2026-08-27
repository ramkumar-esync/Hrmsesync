{{--
    Payslip document.

    Rendered by DomPDF, which supports a conservative subset of CSS — tables and
    floats only, no flexbox or grid. Typeset like a financial statement rather
    than a web page: this sheet gets printed and handed to a bank.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $payslip->period }} — {{ $employee->employeeNumber }}</title>
    <style>
        @page { margin: 26mm 18mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            color: #14161a;
            margin: 0;
        }

        .eyebrow {
            font-size: 7pt;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #6a7280;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .num { text-align: right; white-space: nowrap; }

        /* ---- Masthead ---------------------------------------------------- */
        .masthead td { padding-bottom: 10px; }
        .company-name {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.03em;
        }
        /* Sits above the company name so a wide or a square logo both work. */
        .logo {
            display: block;
            margin-bottom: 5px;
        }
        .company-meta { font-size: 7.5pt; color: #6a7280; line-height: 1.5; }
        .doc-type {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #0b4f53;
        }
        .doc-period { font-size: 9pt; color: #6a7280; }
        .rule-heavy { border-bottom: 2px solid #0b4f53; height: 0; margin-bottom: 14px; }

        /* ---- Employee particulars ---------------------------------------- */
        .particulars { margin-bottom: 16px; }
        .particulars td { padding: 2px 0; font-size: 8.5pt; }
        .particulars .label { color: #6a7280; width: 27%; }
        .particulars .value { font-weight: bold; width: 23%; }

        /* ---- Ledger ------------------------------------------------------ */
        .ledger { margin-bottom: 4px; }
        .ledger > tbody > tr > td { width: 50%; }
        .ledger > tbody > tr > td.left { padding-right: 9mm; }

        .col-head {
            font-size: 7pt;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #0b4f53;
            border-bottom: 1px solid #0b4f53;
            padding-bottom: 4px;
            text-align: left;
        }
        .lines td {
            padding: 4px 0;
            border-bottom: 1px solid #eceef1;
            font-size: 9pt;
        }
        .lines .subtotal td {
            border-bottom: none;
            border-top: 1px solid #d6d9de;
            padding-top: 6px;
            font-weight: bold;
        }
        .empty { color: #9aa1ab; font-style: italic; padding: 6px 0; font-size: 8.5pt; }

        /* ---- Net pay band ------------------------------------------------ */
        .netpay {
            background-color: #0b4f53;
            color: #ffffff;
            margin-top: 18px;
        }
        .netpay td { padding: 12px 14px; }
        .netpay .caption {
            font-size: 7pt;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #a9c8ca;
        }
        .netpay .amount { font-size: 17pt; font-weight: bold; }
        .netpay .words { font-size: 7.5pt; color: #cfe0e1; font-style: italic; }

        /* ---- Employer contributions -------------------------------------- */
        .employer {
            margin-top: 16px;
            background-color: #f4f6f6;
            border-left: 3px solid #b9c9ca;
        }
        .employer .inner { padding: 9px 12px; }
        .employer td.line { font-size: 8.5pt; padding: 2px 0; }
        .note { font-size: 7.5pt; color: #6a7280; margin-top: 3px; }

        /* ---- Footer ------------------------------------------------------ */
        .footer {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #d6d9de;
            font-size: 7pt;
            color: #6a7280;
            line-height: 1.6;
        }
        .ref { font-family: 'DejaVu Sans Mono', monospace; }
    </style>
</head>
<body>

<table class="masthead">
    <tr>
        <td>
            @if (! empty($company['logo']))
                {{-- Height is fixed and width omitted so DomPDF keeps the aspect ratio. --}}
                <img
                    src="{{ $company['logo'] }}"
                    alt="{{ $company['name'] }}"
                    class="logo"
                    style="height: {{ $company['logo_height_mm'] }}mm;"
                >
            @endif
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-meta">
                @if ($company['registration_number'])
                    Registration {{ $company['registration_number'] }}<br>
                @endif
                @if ($company['address'])
                    {{ $company['address'] }}
                @endif
            </div>
        </td>
        <td class="num">
            <div class="doc-type">Payslip</div>
            <div class="doc-period">{{ $payslip->period->label() }}</div>
        </td>
    </tr>
</table>

<div class="rule-heavy"></div>

<table class="particulars">
    <tr>
        <td class="label">Name</td>
        <td class="value">{{ $employee->name }}</td>
        <td class="label">Employee no.</td>
        <td class="value">{{ $employee->employeeNumber }}</td>
    </tr>
    <tr>
        <td class="label">Position</td>
        <td class="value">{{ $employee->jobTitle }}</td>
        <td class="label">Department</td>
        <td class="value">{{ $employee->department ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">EPF no.</td>
        <td class="value">{{ $employee->epfNumber ?? '—' }}</td>
        <td class="label">SOCSO no.</td>
        <td class="value">{{ $employee->socsoNumber ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Income tax no.</td>
        <td class="value">{{ $employee->taxReferenceNumber ?? '—' }}</td>
        <td class="label">Paid into</td>
        <td class="value">
            @if ($employee->bankAccountMasked)
                {{ $employee->bankName }} {{ $employee->bankAccountMasked }}
            @else
                —
            @endif
        </td>
    </tr>
</table>

<table class="ledger">
    <tr>
        <td class="left">
            <table class="lines">
                <tr><th colspan="2" class="col-head">Earnings</th></tr>
                @foreach ($payslip->earnings() as $line)
                    <tr>
                        <td>{{ $line->label() }}</td>
                        <td class="num">{{ number_format($line->amount->minorUnits / 100, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td>Gross pay</td>
                    <td class="num">{{ number_format($payslip->grossPay()->minorUnits / 100, 2) }}</td>
                </tr>
            </table>
        </td>
        <td>
            <table class="lines">
                <tr><th colspan="2" class="col-head">Deductions</th></tr>
                @forelse ($payslip->deductions() as $line)
                    <tr>
                        <td>{{ $line->label() }}</td>
                        <td class="num">{{ number_format($line->amount->minorUnits / 100, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No deductions this period</td></tr>
                @endforelse
                <tr class="subtotal">
                    <td>Total deductions</td>
                    <td class="num">{{ number_format($payslip->totalDeductions()->minorUnits / 100, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="netpay">
    <tr>
        <td>
            <div class="caption">Net pay</div>
            <div class="words">{{ $netInWords }}</div>
        </td>
        <td class="num">
            <div class="amount">{{ $payslip->currency() }} {{ number_format($payslip->netPay()->minorUnits / 100, 2) }}</div>
        </td>
    </tr>
</table>

@if (count($payslip->employerContributions()) > 0)
    <table class="employer">
        <tr>
            <td class="inner">
                <div class="eyebrow">Paid by your employer on your behalf</div>
                <table>
                    @foreach ($payslip->employerContributions() as $line)
                        <tr>
                            <td class="line">{{ $line->label() }}</td>
                            <td class="line num">{{ number_format($line->amount->minorUnits / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </table>
                <div class="note">These amounts are contributed on top of your pay. They are not deducted from it.</div>
            </td>
        </tr>
    </table>
@endif

@if ($payslip->remarks())
    <div class="note" style="margin-top: 14px;">
        <span class="eyebrow">Remarks</span><br>{{ $payslip->remarks() }}
    </div>
@endif

<div class="footer">
    <table>
        <tr>
            <td>
                Payslip reference <span class="ref">{{ strtoupper(substr($payslip->id->value, 0, 8)) }}</span><br>
                Issued {{ $payslip->issuedAt()?->format('j F Y, H:i') ?? '—' }}
                @if ($payslip->supersedes())
                    <br>Replaces payslip <span class="ref">{{ strtoupper(substr($payslip->supersedes()->value, 0, 8)) }}</span>
                @endif
            </td>
            <td class="num">
                Computer-generated. No signature required.<br>
                Queries: contact HR quoting the reference above.
            </td>
        </tr>
    </table>
</div>

</body>
</html>
