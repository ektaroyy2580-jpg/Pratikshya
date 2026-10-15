<?php

namespace App\Services\Ops;

use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\IncidentUpdate;
use App\Models\OperationalIncident;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Incident workflow: reporting, status changes, assignment, comments — all audited. */
class IncidentService
{
    public const PHOTO_DISK = 'local';

    public const PHOTO_TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public function report(User $user, array $data, ?UploadedFile $photo = null): OperationalIncident
    {
        $locationId = (int) $data['location_id'];
        abort_unless(OpsAccess::canOperate($user, $locationId), 403, 'You can only report incidents for your assigned locations.');
        $this->assertBelongs($locationId, $data['service_id'] ?? null, $data['counter_id'] ?? null);

        return DB::transaction(function () use ($user, $data, $photo) {
            $incident = new OperationalIncident(collect($data)->only([
                'location_id', 'counter_id', 'service_id', 'equipment', 'category', 'description', 'priority', 'assigned_technician',
            ])->all());
            $incident->is_internal = ! empty($data['is_internal']) || in_array($data['category'], OperationalIncident::ALWAYS_INTERNAL, true);
            $incident->forceFill(['reported_by' => $user->id, 'reported_at' => now(), 'status' => 'open']);
            if ($photo) {
                $incident->photo_path = $this->storePhoto($photo);
            }
            $incident->save();

            $this->log($incident, $user, 'created', null, 'open', null);
            AuditLog::record('ops.incident.reported', $incident, [
                'category' => $incident->category, 'priority' => $incident->priority,
                'internal' => $incident->is_internal, 'photo' => (bool) $incident->photo_path,
            ]);

            return $incident;
        });
    }

    /**
     * Apply a staff update: optional status change, technician, resolution notes and a comment.
     * Each change is written to the incident timeline and the audit log.
     */
    public function update(OperationalIncident $incident, User $user, array $data): OperationalIncident
    {
        abort_unless(OpsAccess::canOperate($user, $incident->location_id), 403);

        return DB::transaction(function () use ($incident, $user, $data) {
            $incident = OperationalIncident::lockForUpdate()->findOrFail($incident->id);
            $changes = [];

            $to = $data['status'] ?? null;
            if ($to && $to !== $incident->status) {
                if (! $incident->canTransitionTo($to)) {
                    throw ValidationException::withMessages(['status' => "An incident that is {$incident->statusLabel()} cannot move to ".str_replace('_', ' ', $to).'.']);
                }
                $from = $incident->status;
                $incident->status = $to;
                $incident->resolved_at = in_array($to, ['resolved', 'closed'], true) ? ($incident->resolved_at ?? now()) : null;
                $this->log($incident, $user, 'status', $from, $to, null);
                $changes['status'] = [$from, $to];
            }

            if (array_key_exists('assigned_technician', $data) && ($data['assigned_technician'] ?: null) !== $incident->assigned_technician) {
                $incident->assigned_technician = $data['assigned_technician'] ?: null;
                $this->log($incident, $user, 'assignment', null, null, $incident->assigned_technician ? 'Assigned to '.$incident->assigned_technician : 'Technician unassigned');
                $changes['technician'] = true;
            }

            if (array_key_exists('resolution_notes', $data) && ($data['resolution_notes'] ?: null) !== $incident->resolution_notes) {
                $incident->resolution_notes = $data['resolution_notes'] ?: null;
                $changes['resolution_notes'] = true;
            }

            if (! empty($data['comment'])) {
                $this->log($incident, $user, 'comment', null, null, $data['comment']);
                $changes['comment'] = true;
            }

            if ($changes) {
                $incident->save();
                AuditLog::record('ops.incident.updated', $incident, $changes);
            }

            return $incident;
        });
    }

    public function storePhoto(UploadedFile $photo): string
    {
        $info = @getimagesize($photo->getRealPath());
        if (! $info || ! isset(self::PHOTO_TYPES[$info[2]])) {
            throw ValidationException::withMessages(['photo' => 'The photo must be a real JPG, PNG or WebP image.']);
        }
        $name = 'ops/incidents/'.Str::uuid().'.'.self::PHOTO_TYPES[$info[2]];
        Storage::disk(self::PHOTO_DISK)->put($name, file_get_contents($photo->getRealPath()));

        return $name;
    }

    public function log(OperationalIncident $incident, User $user, string $type, ?string $from, ?string $to, ?string $body): IncidentUpdate
    {
        return IncidentUpdate::create([
            'operational_incident_id' => $incident->id, 'author_id' => $user->id,
            'type' => $type, 'from_status' => $from, 'to_status' => $to, 'body' => $body,
        ]);
    }

    /** Service and counter must belong to the incident's location (no cross-location IDs). */
    public function assertBelongs(int $locationId, $serviceId, $counterId): void
    {
        if ($serviceId && ! Service::whereKey($serviceId)->where('location_id', $locationId)->exists()) {
            throw ValidationException::withMessages(['service_id' => 'That service is not at the selected location.']);
        }
        if ($counterId && ! Counter::whereKey($counterId)->where('location_id', $locationId)->exists()) {
            throw ValidationException::withMessages(['counter_id' => 'That counter is not at the selected location.']);
        }
    }
}
