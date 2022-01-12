<?php

namespace MohammadZarifiyan\Queueable;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class QueueableScope implements Scope
{
    protected $extensions = [
        'Active',
        'Inactive',
        'Expiring',
        'Inhale',
        'SetStatus',
        'Expire',
    ];

	public function apply(Builder $builder, Model $model)
	{
		return $builder;
	}

    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{'add' . $extension}($builder);
        }
    }

    public function addActive(Builder $builder)
    {
        $builder->macro('active', function (Builder $builder) {
            return $builder->where('status', '=', true);
        });
    }

    public function addInactive(Builder $builder)
    {
        $builder->macro('inactive', function (Builder $builder) {
            return $builder->where('status', '=', false);
        });
    }

    public function addExpiring(Builder $builder)
    {
        $builder->macro('expiring', function (Builder $builder) {
            return $builder->active()
                ->whereNotNull('expires_in')
                ->whereRaw('expires_in + IFNULL(inactive_for, 0) <= TIME_TO_SEC(TIMEDIFF(NOW(), started_at))');
        });
    }

    public function addInhale(Builder $builder)
    {
        $builder->macro('inhale', function (Builder $builder) {
            return $builder->whereNull('expired_at');
        });
    }

    public function addSetStatus(Builder $builder)
    {
        $builder->macro('setStatus', function (Builder $builder, bool $status, Closure $conditionClosure) {
            $model = $builder->getModel();

            if ($model->status === $status) {
                return false;
            }

            if ($status) {
                if (!$this->activate($model)) {
                    return false;
                }

                $this->inactivateOtherActiveModelOnExists($model, $conditionClosure);
            }
            else {
                if (!$this->inactivate($model)) {
                    return false;
                }

                $this->activateOtherInhaleModelOnExists($model, $conditionClosure);
            }

            return true;
        });
    }

    public function baseConditionalQuery(Model $model, Closure $conditionClosure)
    {
        $query = $model->newQuery();

        $conditionClosure($query);

        return $query;
    }

    public function inactivateOtherActiveModelOnExists(Model $model, Closure $conditionClosure)
    {
        $conditional_query = $this->baseConditionalQuery($model, $conditionClosure);

        $qualified_key_name = $model->getKeyName();

        $previous_active_model = $conditional_query->where($qualified_key_name, '<>', $model->{$qualified_key_name})
            ->active()
            ->first();

        if ($previous_active_model) {
            $this->inactivate($previous_active_model);
        }
    }

    public function activateOtherInhaleModelOnExists(Model $model, Closure $conditionClosure)
    {
        $conditional_query = $this->baseConditionalQuery($model, $conditionClosure);

        $qualified_key_name = $model->getKeyName();

        $first_inhale_model = $conditional_query->where($qualified_key_name, '>', $model->{$qualified_key_name})
            ->inhale()
            ->first();

        if ($first_inhale_model) {
            $this->activate($first_inhale_model);
        }
    }

    public function addExpire(Builder $builder)
    {
        $builder->macro('expire', function (Builder $builder, Closure $conditionClosure) {
            $model = $builder->getModel();

            $result = $model->update([
                'expired_at' => $model->freshTimestamp(),
                'status' => false,
            ]);

            $this->activateOtherInhaleModelOnExists($model, $conditionClosure);

            return $result;
        });
    }

    public function activate(Model $model)
    {
        if ($this->isPendingToStart($model)) {
            return $this->start($model);
        }
        elseif ($this->isPaused($model)) {
            return $this->continue($model);
        }

        return false;
    }

    public function inactivate(Model $model)
    {
        if ($this->isActive($model)) {
            return $this->pause($model);
        }

        return false;
    }

    public function isActive(Model $model): bool
    {
        return $model->status;
    }

    public function isPendingToStart(Model $model): bool
    {
        return is_null($model->started_at);
    }

    public function isPaused(Model $model): bool
    {
        return (bool) $model->paused_at;
    }

    public function isExpired(Model $model): bool
    {
        return (bool) $model->expired_at;
    }

    public function start(Model $model): bool
    {
        return $model->update([
            'started_at' => $model->freshTimestamp(),
            'status' => true,
        ]);
    }

    public function continue(Model $model): bool
    {
        return $model->update([
            'paused_at' => null,
            'continued_at' => $continued_at = $model->freshTimestamp(),
            'inactive_for' => $model->inactive_for + $continued_at->diffInSeconds($model->paused_at),
            'status' => true,
        ]);
    }

    public function pause(Model $model): bool
    {
        return $model->update([
            'paused_at' => $model->freshTimestamp(),
            'continued_at' => null,
            'status' => false,
        ]);
    }
}
