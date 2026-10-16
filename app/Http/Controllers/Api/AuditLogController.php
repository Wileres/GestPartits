<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta de l'auditoria (només administradors), amb filtres i paginació.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $this->authorize('view-audit');

        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [], ['event' => 'acció', 'user_id' => 'usuari', 'from' => 'des de', 'to' => 'fins a']);

        $logs = AuditLog::query()
            ->with('user')
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', 'like', str_replace(['%', '_'], ['\%', '\_'], $event).'%'))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(50);

        return AuditLogResource::collection($logs);
    }
}
