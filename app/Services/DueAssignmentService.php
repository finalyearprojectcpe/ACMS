<?php

namespace App\Services;

use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\User;
use App\StudentStatus;
use Illuminate\Support\Facades\DB;

class DueAssignmentService
{
    public function syncForDue(Due $due): void
    {
        if ($due->status !== Due::StatusPublished) {
            return;
        }

        StudentProfile::query()
            ->where('student_set_id', $due->student_set_id)
            ->where('status', StudentStatus::Active->value)
            ->pluck('id')
            ->each(fn (int $studentProfileId) => $this->createAssignment($due, $studentProfileId));
    }

    public function syncForStudent(StudentProfile $studentProfile): void
    {
        if ($studentProfile->status !== StudentStatus::Active) {
            return;
        }

        Due::query()
            ->where('student_set_id', $studentProfile->student_set_id)
            ->where('status', Due::StatusPublished)
            ->get()
            ->each(fn (Due $due) => $this->createAssignment($due, (int) $studentProfile->id));
    }

    public function archive(Due $due): Due
    {
        return DB::transaction(function () use ($due): Due {
            $due = Due::query()->whereKey($due->id)->lockForUpdate()->firstOrFail();

            if ($due->status === Due::StatusArchived) {
                return $due;
            }

            $due->assignments()
                ->with('payments')
                ->get()
                ->each(fn (StudentDueAssignment $assignment) => $this->closeOpenCheckouts($assignment));

            $due->forceFill([
                'status' => Due::StatusArchived,
                'archived_at' => now(),
            ])->save();

            return $due->refresh();
        });
    }

    public function waive(StudentDueAssignment $assignment, User $actor, ?string $reason): StudentDueAssignment
    {
        return DB::transaction(function () use ($assignment, $actor, $reason): StudentDueAssignment {
            $assignment = StudentDueAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status !== StudentDueAssignment::StatusPending) {
                return $assignment;
            }

            $this->closeOpenCheckouts($assignment);
            $assignment->forceFill([
                'status' => StudentDueAssignment::StatusWaived,
                'paid_at' => null,
                'waived_at' => now(),
                'waived_by_user_id' => $actor->id,
                'waiver_reason' => $reason ?: 'Waived by course adviser.',
                'latest_due_payment_id' => null,
            ])->save();

            return $assignment->refresh();
        });
    }

    public function restore(StudentDueAssignment $assignment): StudentDueAssignment
    {
        return DB::transaction(function () use ($assignment): StudentDueAssignment {
            $assignment = StudentDueAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status !== StudentDueAssignment::StatusWaived) {
                return $assignment;
            }

            $settlement = $assignment->payments()
                ->where('status', DuePayment::StatusSuccess)
                ->orderByDesc('paid_at')
                ->first();

            $assignment->forceFill([
                'status' => $settlement ? StudentDueAssignment::StatusPaid : StudentDueAssignment::StatusPending,
                'amount_kobo' => $settlement?->amount_kobo ?? $assignment->amount_kobo,
                'paid_at' => $settlement?->paid_at,
                'latest_due_payment_id' => $settlement?->id,
                'waived_at' => null,
                'waived_by_user_id' => null,
                'waiver_reason' => null,
            ])->save();

            return $assignment->refresh();
        });
    }

    public function updateAmount(Due $due, int $amountKobo): void
    {
        $due->assignments()
            ->whereIn('status', [
                StudentDueAssignment::StatusPending,
                StudentDueAssignment::StatusWaived,
            ])
            ->with('payments')
            ->get()
            ->each(function (StudentDueAssignment $assignment) use ($amountKobo): void {
                $this->closeOpenCheckouts($assignment);
                $assignment->forceFill(['amount_kobo' => $amountKobo])->save();
            });
    }

    private function createAssignment(Due $due, int $studentProfileId): void
    {
        StudentDueAssignment::query()->firstOrCreate(
            [
                'due_id' => $due->id,
                'student_profile_id' => $studentProfileId,
            ],
            [
                'amount_kobo' => $due->amount_kobo,
                'status' => StudentDueAssignment::StatusPending,
            ],
        );
    }

    private function closeOpenCheckouts(StudentDueAssignment $assignment): void
    {
        $assignment->payments()
            ->where('status', DuePayment::StatusInitialized)
            ->update([
                'status' => DuePayment::StatusAbandoned,
                'active_marker' => null,
                'gateway_response' => 'Checkout closed by the course adviser.',
                'updated_at' => now(),
            ]);
    }
}
