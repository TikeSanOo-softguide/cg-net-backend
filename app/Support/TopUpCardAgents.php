<?php

namespace App\Support;

use App\Models\Agent;

final class TopUpCardAgents
{
    /**
     * Resolve agent CD strings for generation.
     * An empty selection means every non-deleted agent.
     *
     * @param  list<int>  $agentIds
     * @return list<string>
     */
    public static function resolveCodes(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_map('intval', $agentIds)));

        $query = Agent::query()->orderBy('id');

        if ($agentIds !== []) {
            $query->whereIn('id', $agentIds);
        }

        return $query
            ->pluck('cd')
            ->map(fn($cd): string => (string) $cd)
            ->unique()
            ->values()
            ->all();
    }
}
