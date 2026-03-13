<?php

namespace App\Services;

use App\Models\Budget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CurrentBudget
{
    public function current(): Budget
    {
        $query = $this->baseQuery();
        $budgetId = (int) session('active_budget_id', 0);

        if ($budgetId > 0) {
            $budget = (clone $query)->whereKey($budgetId)->first();
            if ($budget) {
                return $budget;
            }
        }

        $budget = (clone $query)->orderBy('name')->first();

        if (! $budget) {
            $budget = Budget::create([
                'user_id' => Auth::id(),
                'name' => 'Budget Principale',
            ]);
        }

        if (! app()->runningInConsole()) {
            session(['active_budget_id' => $budget->id]);
        }

        return $budget;
    }

    public function all(): Collection
    {
        return $this->baseQuery()->orderBy('name')->get();
    }

    public function set(Budget $budget): void
    {
        if ($this->isAccessible($budget)) {
            session(['active_budget_id' => $budget->id]);
        }
    }

    public function isAccessible(Budget $budget): bool
    {
        $userId = Auth::id();

        if ($userId) {
            return (int) $budget->user_id === (int) $userId;
        }

        return $budget->user_id === null;
    }

    private function baseQuery()
    {
        $query = Budget::query();
        $userId = Auth::id();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        return $query;
    }
}
