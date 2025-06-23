<?php

namespace App\Http\Controllers;

use App\Models\WhitelistedIP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WhitelistedIPController extends Controller
{
    /**
     * Display all whitelisted IPs for the authenticated user.
     */
    public function index()
    {
        try {
            $ips = WhitelistedIP::whereUserId(auth()->id())->first(); 
            return get_success_response($ips);
        } catch (\Throwable $th) {
            return get_error_response($th->getMessage());
        }
    }

    /**
     * Store new IPs and allow them via UFW.
     */
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "new_ip.*" => "required|ip",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $user = auth()->user();
            $whitelistedIPs = array_unique($request->new_ip);

            $currentIP = WhitelistedIP::where('user_id', $user->id)->first();

            if ($currentIP) {
                $existingIPs = $currentIP->ip_address ?? [];
                $mergedIPs = array_values(array_unique(array_merge($existingIPs, $whitelistedIPs)));
                $currentIP->ip_address = $mergedIPs;
                $currentIP->save();
            } else {
                WhitelistedIP::create([
                    'user_id' => $user->id,
                    'ip_address' => $whitelistedIPs,
                ]);
            }

            // Allow all new IPs via UFW
            foreach ($whitelistedIPs as $ip) {
                $this->allowFirewallIP($ip);
            }

            return get_success_response(['message' => 'IP addresses whitelisted and added to firewall.']);
        } catch (\Throwable $th) {
            Log::error($th);
            return get_error_response(['error' => 'Failed to whitelist IPs.']);
        }
    }

    /**
     * Update an existing whitelist record.
     */
    public function update(Request $request, WhitelistedIP $whitelistedIP)
    {
        try {
            $validate = Validator::make($request->all(), [
                "new_ip.*" => "required|ip",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $user = auth()->user();

            if ($whitelistedIP->user_id !== $user->id) {
                return get_error_response(['error' => 'Unauthorized'], 403);
            }

            $currentIPs = $whitelistedIP->ip_address ?? [];
            $newIPs = array_unique($request->new_ip);
            $mergedIPs = array_merge($currentIPs, array_diff($newIPs, $currentIPs));

            $whitelistedIP->update(['ip_address' => $mergedIPs]);

            // Allow any new IPs via UFW
            foreach ($newIPs as $ip) {
                $this->allowFirewallIP($ip);
            }

            return get_success_response(['message' => 'IP addresses updated and added to firewall.']);
        } catch (\Throwable $th) {
            Log::error($th);
            return get_error_response(['error' => 'Failed to update IP addresses.']);
        }
    }

    /**
     * Remove a specific IP from whitelist and UFW.
     */
    public function destroy($ipAddress)
    {
        try {
            $whitelistedIP = WhitelistedIP::whereUserId(auth()->id())->first();

            if (!$whitelistedIP) {
                return get_error_response(['error' => 'No whitelisted IPs found'], 404);
            }

            if (!in_array($ipAddress, $whitelistedIP->ip_address)) {
                return get_error_response(['error' => 'IP address not found'], 400);
            }

            $whitelistedIP->ip_address = array_values(array_diff($whitelistedIP->ip_address, [$ipAddress]));
            $whitelistedIP->save();

            // Remove IP from firewall
            $this->removeFirewallIP($ipAddress);

            return get_success_response(['message' => 'IP address removed from whitelist and firewall.']);
        } catch (\Throwable $th) {
            Log::error($th);
            return get_error_response(['error' => 'Failed to remove IP address.']);
        }
    }

    /**
     * Allow IP through UFW firewall.
     */
    protected function allowFirewallIP(string $ip): bool
    {
        exec("sudo ufw allow from $ip", $output, $status);
        Log::info("UFW allow $ip: status=$status", ['output' => $output]);
        return $status === 0;
    }

    /**
     * Remove IP from UFW firewall.
     */
    protected function removeFirewallIP(string $ip): bool
    {
        exec("sudo ufw delete allow from $ip", $output, $status);
        Log::info("UFW delete $ip: status=$status", ['output' => $output]);
        return $status === 0;
    }
}
