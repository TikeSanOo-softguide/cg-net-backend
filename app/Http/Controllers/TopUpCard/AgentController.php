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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $cardSearch = trim((string) $request->string('card_search'));
        $batch = $request->has('batch')
            ? $request->string('batch')->toString()
            : (string) $request->session()->get('top_up_card_agent_batch', '');
        $agent = $request->string('agent')->toString();
        $status = $request->string('status')->toString();
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
            ->where('top_up_card.status', '!=', TopUpCardStatus::Pending)
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
            'batches' => Batch::query()
                ->select(['id', 'batch_no'])
                ->whereHas('topUpCards', fn($query) => $query->where('status', '!=', TopUpCardStatus::Pending))
                ->latest('id')
                ->get(),
            'filters' => [
                'search' => $search,
                'card_search' => $cardSearch,
                'batch' => $batch,
                'agent' => $agent,
                'status' => $status,
            ],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $stream = fopen($request->file('file')->getRealPath(), 'r');

        if ($stream === false) {
            return back()->with('error', 'The CSV file could not be read.');
        }

        $headers = fgetcsv($stream);
        $requiredHeaders = ['serial_no', 'pin', 'amount', 'expires_at', 'status'];

        if ($headers === false || array_map('strtolower', $headers) !== $requiredHeaders) {
            fclose($stream);

            return back()->with('error', 'The CSV headers are invalid.');
        }

        $serials = [];
        $line = 1;

        while (($row = fgetcsv($stream)) !== false) {
            $line++;

            if (count($row) !== count($requiredHeaders) || trim((string) $row[0]) === '' || trim((string) $row[4]) !== TopUpCardStatus::Pending->value) {
                fclose($stream);

                return back()->with('error', "The CSV contains an invalid row at line {$line}.");
            }

            $serial = trim((string) $row[0]);

            if (isset($serials[$serial])) {
                fclose($stream);

                return back()->with('error', "The CSV contains a duplicate serial number at line {$line}.");
            }

            $serials[$serial] = true;
        }

        fclose($stream);

        if ($serials === []) {
            return back()->with('error', 'The CSV file contains no cards.');
        }

        $importedBatchId = DB::transaction(function () use ($serials): int {
            $cards = TopUpCard::query()
                ->whereIn('serial_no', array_keys($serials))
                ->where('status', TopUpCardStatus::Pending)
                ->lockForUpdate()
                ->get();

            if ($cards->count() !== count($serials)) {
                abort(422, 'The CSV contains cards that are missing or are no longer pending.');
            }

            $batchIds = $cards->pluck('batch_id')->filter()->unique();

            if ($batchIds->count() !== 1) {
                abort(422, 'The CSV must contain cards from one batch.');
            }

            TopUpCard::query()
                ->whereIn('id', $cards->modelKeys())
                ->update(['status' => TopUpCardStatus::Active]);

            return (int) $batchIds->first();
        });

        $request->session()->put('top_up_card_agent_batch', $importedBatchId);

        return redirect()
            ->route('top-up-cards.agents', ['batch' => $importedBatchId])
            ->with('success', 'Top-up cards imported successfully.');
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $agent = Agent::withTrashed()->where('name', $request->string('name')->toString())->first();

        if ($agent?->trashed()) {
            $agent->restore();
            $agent->update($request->validated());
        } else {
            $agent = Agent::query()->create($request->validated());
        }

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