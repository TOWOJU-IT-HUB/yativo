<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\User;
use Creatydev\Plans\Models\PlanModel;
use Illuminate\Http\Request;

class PlansController extends Controller
{

    public function index()
    {
        $user = auth()->user();
        $currentPlan = $user->activeSubscription();
        if (!$currentPlan) {
            $plan = PlanModel::where('price', 0)->latest()->first();
            if($plan && $user->subscribeTo($plan, 30, true)) {
                $currentPlan = $user->activeSubscription();
            }            
        }
        return get_success_response($currentPlan->makeHidden('model_type', 'payment_method', 'model_id'));
    }

    public function plans()
    {
        try {
            $plans = PlanModel::with('features')->get();
            return get_success_response($plans);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * upgrade or downgrade a business plan
     * 
     * @param PlanModel $plan
     */
    public function subscribe(Request $request, $planId)
    {
        try {
            $currentPlan = auth()->user()->activeSubscription();

            if ((int) $planId === (int) $currentPlan->plan_id) {
                return get_error_response(['error' => "You're already subscribed to the selected plan"]);
            }

            if ((int)$planId == 3) {
                return get_error_response(['error' => 'Please contact support to activate this plan']);
            }

            $user = User::whereId(active_user())->first();

            $plan = PlanModel::whereId($planId)->first();
            $wallet = $user->getWallet('USD');

            if (!$plan) {
                return get_error_response(['error' => 'Unknown plan selected']);
            }

            if (
                !$wallet->withdraw($plan->price, [
                    "purpose" => "Subscription to $plan->name Business Plan"
                ])
            );

            if ($plan->price < 1) {
                $request->merge([
                    'duration' => 30,
                    'auto_renew' => true
                ]);
            }

            $duration = $request->duration ?? 30;
            $renewal = $request->auto_renew ?? true;
            $subscription = $user->subscribeTo($plan, $duration, $renewal);

            if ($subscription) {
                return get_success_response($subscription);
            }

            return get_error_response(["error" => "Subscription failed. please recheck you're not already subscribed to this plan"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * upgrade or downgrade a business plan
     */
    public function upgrade(Request $request, $newPlanId)
    {
        try {
            $newPlan = PlanModel::findOrFail($newPlanId);
            $user = $request->user();
            $wallet = $user->getWallet('USD');

            if ((int)$newPlanId == 3) {
                return get_error_response(['error' => 'Please contact support to activate this plan']);
            }

            if (!$newPlan) {
                return get_error_response(['error' => 'Unknown plan selected']);
            }

            if (
                !$wallet->withdraw($newPlan->price, [
                    "purpose" => "Subscription to $newPlan->name Business Plan, please confirm you have enough funds inyour USD wallet to upgrade"
                ])
            );

            $currentSubscription = $user->activeSubscription();

            if ($currentSubscription) {
                $user->upgradeCurrentPlanTo($newPlan);
            } else {
                // subscribe to new plan
                $user->subscribeTo($newPlan);
            }

            return get_success_response(['message' => 'Subscription upgraded successfully']);
            // return get_error_response(['message' => 'No active subscription found'], 404);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
}
