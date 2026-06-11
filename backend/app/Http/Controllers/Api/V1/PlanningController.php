<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ChecklistUpdated;
use App\Events\ExpenseUpdated;
use App\Events\ItineraryUpdated;
use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\Expense;
use App\Models\ExpenseShare;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\Trip;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    // ─────────────────────────────────────────
    //  Guards & formatters
    // ─────────────────────────────────────────

    private function isMember(Trip $trip): bool
    {
        return $trip->joinedMembers()->where('user_id', auth()->id())->exists();
    }

    private function formatItinerary(Trip $trip): array
    {
        return $trip->itineraryDays()
            ->with(['items' => fn($q) => $q->with('creator')->orderBy('order_index')])
            ->orderBy('date')
            ->get()
            ->values()
            ->map(fn($day, $index) => [
                'id'         => $day->id,
                'day_number' => $index + 1,
                'date'       => $day->date->format('Y-m-d'),
                'items'      => $day->items->map(fn($item) => [
                    'id'          => $item->id,
                    'title'       => $item->title,
                    'time'        => $item->time,
                    'location'    => $item->location,
                    'latitude'    => $item->latitude,
                    'longitude'   => $item->longitude,
                    'notes'       => $item->notes,
                    'order_index' => $item->order_index,
                    'created_by'  => [
                        'id'   => $item->creator->id,
                        'name' => $item->creator->name,
                    ],
                ])->values()->toArray(),
            ])
            ->toArray();
    }

    private function formatExpenses(Trip $trip): array
    {
        return $trip->expenses()
            ->with(['paidBy', 'shares.user'])
            ->latest()
            ->get()
            ->map(fn($expense) => [
                'id'          => $expense->id,
                'amount'      => $expense->amount,
                'description' => $expense->description,
                'split_type'  => $expense->split_type,
                'paid_by'     => [
                    'id'     => $expense->paidBy->id,
                    'name'   => $expense->paidBy->name,
                    'avatar' => $expense->paidBy->avatar,
                ],
                'shares' => $expense->shares->map(fn($share) => [
                    'id'           => $share->id,
                    'user'         => [
                        'id'     => $share->user->id,
                        'name'   => $share->user->name,
                        'avatar' => $share->user->avatar,
                    ],
                    'share_amount' => $share->share_amount,
                    'is_settled'   => $share->is_settled,
                ])->values()->toArray(),
            ])
            ->values()
            ->toArray();
    }

    private function calculateSettlement(Trip $trip): array
    {
        // Aggregate unsettled shares, excluding the payer's own share
        $unsettled = ExpenseShare::whereHas('expense', fn($q) => $q->where('trip_id', $trip->id))
            ->where('is_settled', false)
            ->with(['user', 'expense.paidBy'])
            ->get()
            ->filter(fn($share) => $share->user_id !== $share->expense->paid_by_id);

        if ($unsettled->isEmpty()) return [];

        $debts = [];
        foreach ($unsettled as $share) {
            $key = $share->user_id . '-' . $share->expense->paid_by_id;
            if (!isset($debts[$key])) {
                $debts[$key] = [
                    'from'   => ['id' => $share->user->id, 'name' => $share->user->name],
                    'to'     => ['id' => $share->expense->paidBy->id, 'name' => $share->expense->paidBy->name],
                    'amount' => 0,
                ];
            }
            $debts[$key]['amount'] += $share->share_amount;
        }

        return array_values($debts);
    }

    private function formatChecklist(Trip $trip): array
    {
        return $trip->checklistItems()
            ->with(['assignedTo', 'completedBy'])
            ->orderBy('order_index')
            ->get()
            ->map(fn($item) => [
                'id'           => $item->id,
                'title'        => $item->title,
                'is_completed' => $item->is_completed,
                'order_index'  => $item->order_index,
                'assigned_to'  => $item->assignedTo ? [
                    'id'     => $item->assignedTo->id,
                    'name'   => $item->assignedTo->name,
                    'avatar' => $item->assignedTo->avatar,
                ] : null,
                'completed_by' => $item->completedBy ? [
                    'id'   => $item->completedBy->id,
                    'name' => $item->completedBy->name,
                ] : null,
                'completed_at' => $item->completed_at,
            ])
            ->values()
            ->toArray();
    }

    // ─────────────────────────────────────────
    //  Itinerary
    // ─────────────────────────────────────────

    public function getItinerary(Trip $trip)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        return response()->json(['itinerary' => $this->formatItinerary($trip)]);
    }

    public function addDay(Request $request, Trip $trip)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $validated = $request->validate([
            'date' => [
                'required',
                'date',
                'after_or_equal:' . $trip->start_date->format('Y-m-d'),
                'before_or_equal:' . $trip->end_date->format('Y-m-d'),
            ],
        ]);

        $nextDay = ($trip->itineraryDays()->max('day_number') ?? 0) + 1;

        ItineraryDay::create([
            'trip_id'    => $trip->id,
            'day_number' => $nextDay,
            'date'       => $validated['date'],
        ]);

        $itinerary = $this->formatItinerary($trip);
        broadcast(new ItineraryUpdated($trip, $itinerary));

        return response()->json(['itinerary' => $itinerary], 201);
    }

    public function addItineraryItem(Request $request, Trip $trip, ItineraryDay $day)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $validated = $request->validate([
            'title'     => 'required|string|max:255',
            'time'      => 'nullable|string|max:10',
            'location'  => 'nullable|string|max:500',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $nextOrder = ($day->items()->max('order_index') ?? -1) + 1;

        ItineraryItem::create([
            ...$validated,
            'itinerary_day_id' => $day->id,
            'order_index'      => $nextOrder,
            'created_by'       => auth()->id(),
        ]);

        $itinerary = $this->formatItinerary($trip);
        broadcast(new ItineraryUpdated($trip, $itinerary));

        return response()->json(['itinerary' => $itinerary], 201);
    }

    public function updateItineraryItem(Request $request, Trip $trip, ItineraryItem $item)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $validated = $request->validate([
            'title'     => 'sometimes|string|max:255',
            'time'      => 'sometimes|nullable|string|max:10',
            'location'  => 'sometimes|nullable|string|max:500',
            'latitude'  => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'notes'     => 'sometimes|nullable|string|max:1000',
        ]);

        $item->update($validated);

        $itinerary = $this->formatItinerary($trip);
        broadcast(new ItineraryUpdated($trip, $itinerary));

        return response()->json(['itinerary' => $itinerary]);
    }

    public function deleteItineraryItem(Trip $trip, ItineraryItem $item)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $item->delete();

        $itinerary = $this->formatItinerary($trip);
        broadcast(new ItineraryUpdated($trip, $itinerary));

        return response()->json(['itinerary' => $itinerary]);
    }

    public function deleteDay(Trip $trip, ItineraryDay $day)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $day->delete(); // cascades to items

        // Re-number days sequentially by date order
        $trip->itineraryDays()->orderBy('date')->get()
            ->each(fn($d, $i) => $d->update(['day_number' => $i + 1]));

        $itinerary = $this->formatItinerary($trip);
        broadcast(new ItineraryUpdated($trip, $itinerary));

        return response()->json(['itinerary' => $itinerary]);
    }

    // ─────────────────────────────────────────
    //  Expenses
    // ─────────────────────────────────────────

    public function getExpenses(Trip $trip)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        return response()->json([
            'expenses'   => $this->formatExpenses($trip),
            'settlement' => $this->calculateSettlement($trip),
        ]);
    }

    public function addExpense(Request $request, Trip $trip)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $validated = $request->validate([
            'amount'      => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'paid_by_id'  => 'required|exists:users,id',
            'split_type'  => 'in:equal,custom',
            'shares'      => 'required_if:split_type,custom|array',
            'shares.*.user_id'      => 'required_with:shares|exists:users,id',
            'shares.*.share_amount' => 'required_with:shares|integer|min:0',
        ]);

        $splitType = $validated['split_type'] ?? 'equal';

        $expense = Expense::create([
            'trip_id'     => $trip->id,
            'paid_by_id'  => $validated['paid_by_id'],
            'amount'      => $validated['amount'],
            'description' => $validated['description'],
            'split_type'  => $splitType,
        ]);

        if ($splitType === 'equal') {
            $members    = $trip->joinedMembers()->get();
            $perPerson  = (int) round($validated['amount'] / $members->count());

            foreach ($members as $member) {
                ExpenseShare::create([
                    'expense_id'   => $expense->id,
                    'user_id'      => $member->id,
                    'share_amount' => $perPerson,
                    'is_settled'   => $member->id === (int) $validated['paid_by_id'],
                ]);
            }
        } else {
            foreach ($validated['shares'] as $shareData) {
                ExpenseShare::create([
                    'expense_id'   => $expense->id,
                    'user_id'      => $shareData['user_id'],
                    'share_amount' => $shareData['share_amount'],
                    'is_settled'   => $shareData['user_id'] === (int) $validated['paid_by_id'],
                ]);
            }
        }

        $expenses   = $this->formatExpenses($trip);
        $settlement = $this->calculateSettlement($trip);
        broadcast(new ExpenseUpdated($trip, $expenses, $settlement));

        return response()->json(['expenses' => $expenses, 'settlement' => $settlement], 201);
    }

    public function settleShare(Trip $trip, ExpenseShare $share)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $share->update(['is_settled' => true]);

        $expenses   = $this->formatExpenses($trip);
        $settlement = $this->calculateSettlement($trip);
        broadcast(new ExpenseUpdated($trip, $expenses, $settlement));

        return response()->json(['expenses' => $expenses, 'settlement' => $settlement]);
    }

    // ─────────────────────────────────────────
    //  Checklist
    // ─────────────────────────────────────────

    public function getChecklist(Trip $trip)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        return response()->json(['items' => $this->formatChecklist($trip)]);
    }

    public function addChecklistItem(Request $request, Trip $trip)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'assigned_to_id' => 'nullable|exists:users,id',
        ]);

        $nextOrder = ($trip->checklistItems()->max('order_index') ?? -1) + 1;

        ChecklistItem::create([
            ...$validated,
            'trip_id'     => $trip->id,
            'order_index' => $nextOrder,
        ]);

        $items = $this->formatChecklist($trip);
        broadcast(new ChecklistUpdated($trip, $items));

        return response()->json(['items' => $items], 201);
    }

    public function updateChecklistItem(Request $request, Trip $trip, ChecklistItem $item)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'assigned_to_id' => 'sometimes|nullable|exists:users,id',
        ]);

        $item->update($validated);

        $items = $this->formatChecklist($trip);
        broadcast(new ChecklistUpdated($trip, $items));

        return response()->json(['items' => $items]);
    }

    public function toggleChecklistItem(Trip $trip, ChecklistItem $item)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        if ($item->is_completed) {
            $item->update([
                'is_completed'    => false,
                'completed_by_id' => null,
                'completed_at'    => null,
            ]);
        } else {
            $item->update([
                'is_completed'    => true,
                'completed_by_id' => auth()->id(),
                'completed_at'    => now(),
            ]);
        }

        $items = $this->formatChecklist($trip);
        broadcast(new ChecklistUpdated($trip, $items));

        return response()->json(['items' => $items]);
    }

    public function deleteChecklistItem(Trip $trip, ChecklistItem $item)
    {
        if (!$this->isMember($trip)) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $item->delete();

        $items = $this->formatChecklist($trip);
        broadcast(new ChecklistUpdated($trip, $items));

        return response()->json(['items' => $items]);
    }
}
