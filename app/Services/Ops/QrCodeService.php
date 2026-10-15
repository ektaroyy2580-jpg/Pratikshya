<?php

namespace App\Services\Ops;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Public QR slugs. A QR code only identifies a destination (/q/{code});
 * it never carries credentials, and joining still requires login + server validation.
 */
class QrCodeService
{
    public function codeFor(Service|Location $model): string
    {
        if (! $model->qr_code) {
            $model->forceFill(['qr_code' => $this->freshCode($model)])->save();
        }

        return $model->qr_code;
    }

    public function urlFor(Service|Location $model): string
    {
        return $model instanceof Service
            ? route('ops.qr.landing', $this->codeFor($model))
            : route('ops.qr.location', $this->codeFor($model));
    }

    /** Invalidate printed posters by issuing a new slug (old one 404s). */
    public function rotate(Service|Location $model): string
    {
        $old = $model->qr_code;
        $model->forceFill(['qr_code' => $this->freshCode($model)])->save();
        AuditLog::record('ops.qr.rotated', $model, ['previous_suffix' => $old ? substr($old, -3) : null]);

        return $model->qr_code;
    }

    private function freshCode(Model $model): string
    {
        do {
            $code = Str::lower(Str::random(10));
        } while ($model->newQuery()->where('qr_code', $code)->exists());

        return $code;
    }
}
