<?php

namespace App\Http\Controllers;

use App\Models\WhitelistedIP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WhitelistedIPController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Get all whitelisted IPs for the authenticated user
            $ips = WhitelistedIP::whereUserId(auth()->id())->first(); 
            return get_success_response($ips); // Use custom success response
        } catch (\Throwable $th) {
            return get_error_response($th->getMessage()); // Use custom error response
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate request parameters
            $validate = Validator::make($request->all(), [
                "new_ip.*" => "required|ip",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $user = auth()->user();
            $currentIP = WhitelistedIP::where('user_id', $user->id)->first();

            // Initialize array to store new whitelisted IPs
            $whitelistedIPs = array_unique($request->new_ip);  // Ensure unique IPs

            if ($currentIP) {
                // Merge current IPs and new IPs, ensuring no duplicates
                $currentIPsArray = $currentIP->ip_address ?? [];
                $mergedIPs = array_values(array_unique(array_merge($currentIPsArray, $whitelistedIPs)));
                $currentIP->ip_address = $mergedIPs;
                $currentIP->save();
            } else {
                // Create new record if no current IPs exist for the user
                WhitelistedIP::create([
                    'user_id' => $user->id,
                    'ip_address' => $whitelistedIPs,
                ]);
            }

            return get_success_response([
                'message' => 'IP addresses whitelisted successfully'
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WhitelistedIP $whitelistedIP)
    {
        try {
            // Validate request parameters
            $validate = Validator::make($request->all(), [
                "new_ip.*" => "required|ip",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $user = auth()->user();

            // Check if the user owns the whitelisted IP
            if ($whitelistedIP->user_id !== $user->id) {
                return get_error_response(['error' => 'Unauthorized'], 403);
            }

            // Get the current IPs
            $currentIPs = $whitelistedIP->ip_address ?? [];
            $newIPs = array_unique($request->new_ip);  // Ensure unique new IPs

            // Merge the current IPs with the new IPs, avoiding duplicates
            $whitelistedIPs = array_merge($currentIPs, array_diff($newIPs, $currentIPs));

            // Update the record
            $whitelistedIP->update(['ip_address' => $whitelistedIPs]);

            return get_success_response([
                'message' => 'IP addresses updated successfully'
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return get_error_response('Failed to update IP addresses');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($ipAddress)
    {
        try {
            // Get the user's whitelisted IP record
            $whitelistedIP = WhitelistedIP::whereUserId(auth()->id())->first();

            if (!$whitelistedIP) {
                return get_error_response(['error' => 'No whitelisted IPs found'], 404);
            }

            // Check if the IP address exists in the array
            if (!in_array($ipAddress, $whitelistedIP->ip_address)) {
                return get_error_response(['error' => 'IP address not found'], 400);
            }

            // Remove the IP address
            $whitelistedIP->ip_address = array_values(array_diff($whitelistedIP->ip_address, [$ipAddress]));
            $whitelistedIP->save();

            return get_success_response(['message' => 'IP address removed from whitelist successfully']);
        } catch (\Throwable $th) {
            Log::error($th);
            return get_error_response(['error' => 'Failed to remove IP address'], 500);
        }
    }
}
