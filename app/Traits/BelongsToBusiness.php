<?php

namespace App\Traits;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness(): void
    {
        static::creating(function (Model $model) {
            if (! $model->business_id) {
                $activeBusiness = request()->attributes->get('active_business');
                if ($activeBusiness instanceof Business) {
                    $model->business_id = $activeBusiness->id;
                }
            }
        });

        static::addGlobalScope('business_scope', function (Builder $builder) {
            $activeBusiness = request()->attributes->get('active_business');
            if ($activeBusiness instanceof Business) {
                $builder->where($builder->getQuery()->from . '.business_id', $activeBusiness->id);
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
