<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpCard\AssignAgentRequest;
use App\Http\Requests\TopUpCard\AssignCardsToAgentRequest;
use App\Http\Requests\TopUpCard\StoreAgentRequest;
use App\Http\Requests\TopUpCard\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Batch;
use App\Models\TopUpCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $cardSearch = trim((string) $request->string('card_search'));
        $latestBatch = Batch::query()->latest('id')->first(['id', 'batch_no']);
        $batch = $request->has('batch')
            ? $request->string('batch')->toString()
            : (string) ($latestBatch?->id ?? '');
        $agent = $request->string('agent')->toString();
        $status = $request->has('status')
            ? $request->string('status')->toString()
            : TopUpCardStatus::Pending->value;
        $agents = Agent::query()
            ->withCount('topUpCards')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->get();

        $cards = TopUpCard::query()
            ->select('top_up_card.*')
            ->with('agent:id,name')
            ->leftJoin('batches', 'batches.id', '=', 'top_up_card.batch_id')
            ->when($cardSearch !== '', fn($query) => $query->where('serial_no', 'like', '%' . $cardSearch . '%'))
            ->when($batch !== '', fn($query) => $query->where('batch_id', (int) $batch))
            ->when($agent === 'unassigned', fn($query) => $query->whereNull('agent_id'))
            ->when($agent !== '' && $agent !== 'unassigned', fn($query) => $query->where('agent_id', (int) $agent))
            ->when(in_array($status, array_column(TopUpCardStatus::cases(), 'value'), true), fn($query) => $query->where('top_up_card.status', $status))
            ->orderBy('batches.batch_no')
            ->orderBy('top_up_card.serial_no')
            ->get()
            ->map(fn(TopUpCard $card) => $this->cardPayload($card))
            ->values();

        return Inertia::render('TopUpCards/Agent', [
            'agents' => $agents,
            'cards' => $cards,
            'batches' => Batch::query()->select(['id', 'batch_no'])->latest('id')->get(),
            'filters' => [
                'search' => $search,
                'card_search' => $cardSearch,
                'batch' => $batch,
                'agent' => $agent,
                'status' => $status,
            ],
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $agent = Agent::query()->create($request->validated());
        activity('top-up-cards')->causedBy($request->user())->performedOn($agent)->event('created')->log('agent_created');

        return back()->with('success', 'Agent created successfully.');
    }

    public function update(UpdateAgentRequest $request, Agent $agent): RedirectResponse
    {
        $agent->update($request->validated());

        return back()->with('success', 'Agent updated successfully.');
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->delete();

        return back()->with('success', 'Agent deleted successfully.');
    }

    public function assign(AssignAgentRequest $request, TopUpCard $topUpCard): RedirectResponse
    {
        $data = $request->validated();

        if ($topUpCard->redeemed_at !== null || $topUpCard->status === TopUpCardStatus::Used) {
            return back()->with('error', 'A redeemed card cannot be reassigned.');
        }

        $attributes = [
            'agent_id' => $data['agent_id'],
            'status' => $data['agent_id'] === null
                ? TopUpCardStatus::Pending
                : TopUpCardStatus::Active,
        ];

        $topUpCard->update($attributes);

        return back()->with('success', 'Top-up card assigned successfully.');
    }

    public function assignAgent(AssignCardsToAgentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $cards = TopUpCard::query()->whereIn('id', $data['card_ids'])
            ->whereNull('redeemed_at')->where('status', '!=', TopUpCardStatus::Used)->get();

        if ($cards->count() !== count($data['card_ids'])) {
            return back()->with('error', 'One or more selected cards cannot be assigned.');
        }

        $attributes = [
            'agent_id' => $data['agent_id'],
            'status' => $data['agent_id'] === null
                ? TopUpCardStatus::Pending
                : TopUpCardStatus::Active,
        ];

        TopUpCard::query()->whereKey($cards->modelKeys())->update($attributes);

        return back()->with('success', 'Top-up cards assigned successfully.');
    }

    private function cardPayload(TopUpCard $card): array
    {
        return [
            'id' => $card->id,
            'serial_no' => $card->serial_no,
            'amount' => $card->amount,
            'status' => $card->status->value,
            'expires_at' => $card->expires_at?->toDateString(),
            'agent_id' => $card->agent_id,
            'agent' => $card->agent?->name,
            'batch_no' => $card->batch?->batch_no,
        ];
    }
}