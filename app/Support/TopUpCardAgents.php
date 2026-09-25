<?php

namespace App\Support;

use App\Models\Agent;

final class TopUpCardAgents
{
    /**
     * @param  list<int>  $agentIds
     * @return list<string>
     */
    public static function resolveCodes(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_map('intval', $agentIds)));

        if ($agentIds === []) {
            return ['88'];
        }

        $codes = Agent::query()
            ->whereIn('id', $agentIds)
            ->orderBy('id')
            ->pluck('cd')
            ->map(fn($cd): string => (string) $cd)
            ->unique()
            ->values()
            ->all();

        return $codes === [] ? ['88'] : $codes;
    }
}
