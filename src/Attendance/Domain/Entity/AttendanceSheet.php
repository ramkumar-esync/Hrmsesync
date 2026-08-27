<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\Entity;

use Carbon\CarbonImmutable;
use HR\Attendance\Domain\Exception\LeaveDoesNotReconcile;
use HR\Attendance\Domain\Exception\SheetNotEditable;
use HR\Attendance\Domain\Service\LeaveVerifier;
use HR\Attendance\Domain\ValueObject\AttendanceEntry;
use HR\Attendance\Domain\ValueObject\AttendancePeriod;
use HR\Attendance\Domain\ValueObject\AttendanceSheetId;
use HR\Attendance\Domain\ValueObject\SheetStatus;

/**
 * An employee's attendance for one month: the rows they report, and where the
 * sheet is in its review cycle.
 *
 * The employee builds it up in Draft, submits it, and HR either approves it or
 * returns it with a note for another pass. The two rules the aggregate protects
 * are that only an editable sheet can change, and that every leave row must line
 * up with approved leave before the sheet can leave the employee's hands.
 */
final class AttendanceSheet
{
    /** @var list<AttendanceEntry> */
    private array $entries;

    private function __construct(
        private readonly AttendanceSheetId $id,
        private readonly string $employeeId,
        private readonly AttendancePeriod $period,
        private SheetStatus $status,
        array $entries,
        private ?CarbonImmutable $submittedAt,
        private ?CarbonImmutable $decidedAt,
        private ?string $decidedBy,
        private ?string $decisionNote,
    ) {
        $this->entries = array_values($entries);
    }

    public static function start(
        AttendanceSheetId $id,
        string $employeeId,
        AttendancePeriod $period,
    ): self {
        return new self(
            id: $id,
            employeeId: $employeeId,
            period: $period,
            status: SheetStatus::Draft,
            entries: [],
            submittedAt: null,
            decidedAt: null,
            decidedBy: null,
            decisionNote: null,
        );
    }

    /**
     * @param  list<AttendanceEntry>  $entries
     */
    public static function reconstitute(
        AttendanceSheetId $id,
        string $employeeId,
        AttendancePeriod $period,
        SheetStatus $status,
        array $entries,
        ?CarbonImmutable $submittedAt,
        ?CarbonImmutable $decidedAt,
        ?string $decidedBy,
        ?string $decisionNote,
    ): self {
        return new self(
            $id, $employeeId, $period, $status, $entries,
            $submittedAt, $decidedAt, $decidedBy, $decisionNote,
        );
    }

    /**
     * Replace the sheet's rows wholesale. The employee edits the whole month and
     * saves it as one set, so a replace is simpler and less error-prone than
     * tracking individual row edits.
     *
     * @param  list<AttendanceEntry>  $entries
     */
    public function replaceEntries(array $entries): void
    {
        $this->guardEditable();

        $seen = [];
        foreach ($entries as $entry) {
            if (! $this->period->contains($entry->date)) {
                throw SheetNotEditable::because(
                    $entry->date->toDateString().' is outside '.$this->period->label().'.',
                );
            }

            $key = $entry->date->toDateString();
            if (isset($seen[$key])) {
                throw SheetNotEditable::because('Each date can appear only once: '.$key.'.');
            }
            $seen[$key] = true;
        }

        usort($entries, static fn (AttendanceEntry $a, AttendanceEntry $b) => $a->date <=> $b->date);

        $this->entries = array_values($entries);

        // Editing after a return puts the sheet back to a clean draft.
        if ($this->status === SheetStatus::Returned) {
            $this->status = SheetStatus::Draft;
        }
    }

    /**
     * Hand the sheet to HR. Every leave row is reconciled against approved leave
     * first — this is the check that stops someone recording "annual leave" on a
     * day they never had approved.
     */
    public function submit(LeaveVerifier $leave, CarbonImmutable $now): void
    {
        $this->guardEditable();

        if ($this->entries === []) {
            throw SheetNotEditable::because('Add at least one row before submitting.');
        }

        $this->reconcileLeave($leave);

        $this->status = SheetStatus::Submitted;
        $this->submittedAt = $now;
        $this->decidedAt = null;
        $this->decidedBy = null;
        $this->decisionNote = null;
    }

    public function approve(string $approverEmployeeId, CarbonImmutable $now, ?string $note = null): void
    {
        $this->guardAwaitingDecision();

        $this->status = SheetStatus::Approved;
        $this->decidedAt = $now;
        $this->decidedBy = $approverEmployeeId;
        $this->decisionNote = $this->clean($note);
    }

    public function returnForChanges(string $approverEmployeeId, CarbonImmutable $now, string $note): void
    {
        $this->guardAwaitingDecision();

        if (trim($note) === '') {
            throw SheetNotEditable::because('Say what needs changing when returning a sheet.');
        }

        $this->status = SheetStatus::Returned;
        $this->decidedAt = $now;
        $this->decidedBy = $approverEmployeeId;
        $this->decisionNote = trim($note);
    }

    private function reconcileLeave(LeaveVerifier $leave): void
    {
        $leaveDates = [];
        foreach ($this->entries as $entry) {
            if ($entry->isLeave()) {
                $leaveDates[] = $entry->date;
            }
        }

        if ($leaveDates === []) {
            return;
        }

        $approved = $leave->approvedLeaveCodesFor($this->employeeId, $leaveDates);

        $problems = [];
        foreach ($this->entries as $entry) {
            if (! $entry->isLeave()) {
                continue;
            }

            $key = $entry->date->toDateString();
            $codes = $approved[$key] ?? [];

            if (! in_array($entry->leaveTypeCode, $codes, true)) {
                $problems[] = $key.' ('.$entry->leaveTypeCode.')';
            }
        }

        if ($problems !== []) {
            throw LeaveDoesNotReconcile::on($problems);
        }
    }

    private function guardEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw SheetNotEditable::because(
                'This sheet is '.$this->status->label().' and cannot be edited.',
            );
        }
    }

    private function guardAwaitingDecision(): void
    {
        if (! $this->status->awaitsDecision()) {
            throw SheetNotEditable::because(
                'This sheet is '.$this->status->label().', so there is nothing to decide.',
            );
        }
    }

    private function clean(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : $note;
    }

    // ---------------------------------------------------------------- getters

    public function id(): AttendanceSheetId
    {
        return $this->id;
    }

    public function employeeId(): string
    {
        return $this->employeeId;
    }

    public function period(): AttendancePeriod
    {
        return $this->period;
    }

    public function status(): SheetStatus
    {
        return $this->status;
    }

    /** @return list<AttendanceEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function totalMinutes(): int
    {
        return array_sum(array_map(static fn (AttendanceEntry $e) => $e->minutes, $this->entries));
    }

    public function submittedAt(): ?CarbonImmutable
    {
        return $this->submittedAt;
    }

    public function decidedAt(): ?CarbonImmutable
    {
        return $this->decidedAt;
    }

    public function decidedBy(): ?string
    {
        return $this->decidedBy;
    }

    public function decisionNote(): ?string
    {
        return $this->decisionNote;
    }
}
