<?php
namespace App\Core;

class Log
{
    public static function write(string $actorType, int $actorId, string $action, string $target = '', $detail = null): void
    {
        Db::insert('logs', [
            'actor_type' => $actorType,
            'actor_id'   => $actorId,
            'action'     => $action,
            'target'     => $target,
            'detail'     => is_string($detail) || $detail === null ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ip'         => Http::ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
