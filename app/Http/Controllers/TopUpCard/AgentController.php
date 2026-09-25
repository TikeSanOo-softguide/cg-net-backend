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
use App\Http\Controllers\InOutManagement\CSV\TopUpCard as TopUpCardCsv;
use App\Support\CsvImportException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $agentQuery = Agent::query()
            ->withCount('topUpCards')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('name', '%' . $search . '%')
                    ->orWhereLike('address', '%' . $search . '%');
            })
            ->orderBy('name');
        $agents = $agentQuery->paginate(15)->withQueryString();
        $agentCds = Agent::query()->pluck('cd')->map(fn($cd): int => (int) $cd)->values();

        return Inertia::render('TopUpCards/Agent', [
            'agents' => $agents,
            'agentCds' => $agentCds,
            'filters' => ['search' => $search],
        ]);
    }

    public function agentAssign(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $cardSearch = trim((string) $request->string('card_search'));
        $batch = $request->has('batch')
            ? $request->string('batch')->toString()
            : (string) $request->session()->get('top_up_card_agent_batch', '');
        $agent = $request->string('agent')->toString();
        $status = $request->string('status')->toString();
        $amount = $request->string('amount')->toString();
        $agents = Agent::query()
            ->withCount('topUpCards')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('name', '%' . $search . '%')
                    ->orWhereLike('address', '%' . $search . '%');
            })
            ->orderBy('name')
            ->get();

        $cardQuery = TopUpCard::query()
            ->select('top_up_card.*')
            ->with('agent:id,name')
            ->leftJoin('batches', 'batches.id', '=', 'top_up_card.batch_id')
            ->where('top_up_card.status', '!=', TopUpCardStatus::Pending)
            ->when($cardSearch !== '', fn($query) => $query->whereLike('serial_no', '%' . $cardSearch . '%'))
            ->when($batch !== '', fn($query) => $query->where('batch_id', (int) $batch))
            ->when($agent === 'unassigned', fn($query) => $query->whereNull('agent_id'))
            ->when($agent !== '' && $agent !== 'unassigned', fn($query) => $query->where('agent_id', (int) $agent))
            ->when($amount !== '' && is_numeric($amount), fn($query) => $query->where('top_up_card.amount', $amount))
            ->when(in_array($status, array_column(TopUpCardStatus::cases(), 'value'), true), fn($query) => $query->where('top_up_card.status', $status))
            ->orderBy('batches.batch_no')
            ->orderBy('top_up_card.serial_no');
        $cards = $cardQuery->paginate(100)->withQueryString()->through(fn(TopUpCard $card) => $this->cardPayload($card));

        $batches = Batch::query()
            ->select(['id', 'batch_no', 'expires_at'])
            ->whereHas('topUpCards', fn($query) => $query->where('status', '!=', TopUpCardStatus::Pending))
            ->withCount(['topUpCards as available_cards_count' => fn($query) => $query
                ->where('status', TopUpCardStatus::Active)
                ->whereNull('agent_id')
                ->whereNull('redeemed_at')])
            ->with(['topUpCards' => fn($query) => $query
                ->select(['batch_id', 'amount'])
                ->where('status', TopUpCardStatus::Active)
                ->whereNull('agent_id')
                ->whereNull('redeemed_at')
                ->distinct()])
            ->latest('id')
            ->get()
            ->map(fn(Batch $batch) => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'expires_at' => $batch->expires_at?->toDateString(),
                'available_cards_count' => $batch->available_cards_count,
                'available_points' => $batch->topUpCards->pluck('amount')->map(fn($amount) => (float) $amount)->unique()->sort()->values(),
            ]);

        $points = TopUpCard::query()
            ->where('status', '!=', TopUpCardStatus::Pending)
            ->distinct()
            ->orderBy('amount')
            ->pluck('amount')
            ->map(fn($point) => (float) $point)
            ->values();

        return Inertia::render('TopUpCards/AgentAssign', [
            'agents' => $agents,
            'cards' => $cards,
            'batches' => $batches,
            'points' => $points,
            'filters' => [
                'search' => $search,
                'card_search' => $cardSearch,
                'batch' => $batch,
                'agent' => $agent,
                'status' => $status,
                'amount' => $amount,
            ],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $importedBatchId = TopUpCardCsv::import($request->file('file'));
        } catch (CsvImportException $exception) {
            return back()->with('import_error', [
                'key' => $exception->translationKey,
                'replace' => $exception->replace,
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('import_error', [
                'key' => 'csv.import_errors.read_failed',
                'replace' => [],
            ]);
        }

        $request->session()->put('top_up_card_agent_batch', $importedBatchId);
        $route = $request->string('return')->toString() === 'assign'
            ? 'top-up-cards.agent-assign'
            : 'top-up-cards.agents';

        return redirect()
            ->route($route)
            ->with('success', 'Top-up cards imported successfully.');
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
        $attributes = [
            'agent_id' => $data['agent_id'],
            'status' => $data['agent_id'] === null
                ? TopUpCardStatus::Pending
                : TopUpCardStatus::Active,
        ];

        TopUpCard::query()
            ->where('batch_id', $data['batch_id'])
            ->where('amount', $data['amount'])
            ->whereNull('redeemed_at')
            ->where('status', TopUpCardStatus::Active)
            ->whereNull('agent_id')
            ->update($attributes);

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