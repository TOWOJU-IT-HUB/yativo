<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Creatydev\Plans\Models\PlanModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlansController extends Controller
{

    public function index()
    {
        $user = auth()->user();
        $currentPlan = $user->activeSubscription();
        if (!$currentPlan) {
            $plan = PlanModel::where('price', 0)->latest()->first();
            if ($plan && $user->subscribeTo($plan, 30, true)) {
                $currentPlan = $user->activeSubscription();
            }
        }
        return get_success_response($currentPlan->makeHidden('model_type', 'payment_method', 'model_id'));
    }

    public function plans()
    {
        try {
            $plans = Plan::get();
            $plans->transform(function ($plan) {
                if ($plan->id == 3) {
                    $plan->price = "Custom";
                }
                $plan->metadata = json_decode($plan->metadata, true);
                return $plan;
            });
            return get_success_response($plans);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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

            if ((int) $planId == 3) {
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
            )
                ;

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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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

            if (!$newPlan) {
                return get_error_response(['error' => 'Unknown plan selected']);
            }

            if (strtolower($newPlan->price) === "custom") {
                return get_error_response(['error' => 'Please contact support to activate this plan']);
            }

            if (
                !$wallet->withdraw($newPlan->price, [
                    "purpose" => "Subscription to $newPlan->name Business Plan, please confirm you have enough funds inyour USD wallet to upgrade"
                ])
            )
                ;

            $currentSubscription = $user->activeSubscription();

            if ($currentSubscription) {
                if ($currentSubscription->plan_id == 1) {
                    $user->cancelCurrentSubscription();
                }

                if ($currentSubscription->plan_id == $newPlan->id) {
                    return get_error_response(['error' => 'You are already subscribed to this plan']);
                }

                $user->upgradeCurrentPlanTo($newPlan, $newPlan->duration, false, true);
                $user->upgradeCurrentPlanTo($newPlan, $newPlan->duration, false, true);
            } else {
                // subscribe to new plan
                $user->subscribeTo($newPlan);
            }

            return get_success_response(['message' => 'Subscription upgraded successfully']);
            // return get_error_response(['message' => 'No active subscription found'], 404);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }


    /**
     * Customer to upgrade or downgrade subscription plan
     * @param mixed $planId
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function changePlan(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "user_id" => "required|exists:users,id",
                "plan_id" => "required"
            ]);

            if($request->fails()){
                return get_error_response(['error' => $validator->errors()], 422, "Validation Error");
            }

            $user = User::findOrFail($request->user_id); // Get authenticated user directly
            $plan = Plan::findOrFail($request->plan_id); // Find plan or return 404

            // Check if user is currently subscribed to any plan
            if ($user->subscribed()) {
                // Cancel current subscription
                $user->subscription()->cancel();
            }

            // Subscribe to new plan
            if ($user->newSubscription($plan->name)->create()) {
                return back()->with('success', 'Plan subscription was successful');
            }

            return back()->with('error', 'subscription_change_failed');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

}
