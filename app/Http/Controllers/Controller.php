<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\CurrentBudget;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    protected function currentBudget(): Budget
    {
        return app(CurrentBudget::class)->current();
    }

    protected function currentBudgetId(): int
    {
        return $this->currentBudget()->id;
    }

    protected function ensureInCurrentBudget(Model $model): void
    {
        if ((int) ($model->budget_id ?? 0) !== $this->currentBudgetId()) {
            abort(404);
        }
    }
}
