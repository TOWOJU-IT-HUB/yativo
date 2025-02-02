<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserMetaRequest;
use App\Http\Requests\UpdateUserMetaRequest;
use App\Models\UserMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Class UserMetaController
 * @package App\Http\Controllers
 */
class UserMetaController extends Controller
{
    /**
     * Get all user meta
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $userMeta = UserMeta::all();
            return get_success_response($userMeta);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user meta by id
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $this->validate($request, [
            'id' => ['required', 'integer', Rule::exists('user_metas', 'id')],
        ]);

        try {
            $userMeta = UserMeta::whereUserId(active_user())->whereId($id)->first();
            return get_success_response($userMeta);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Store new user meta
     *
     * @param StoreUserMetaRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUserMetaRequest $request)
    {
        $this->validate($request, [
            'meta_key' => ['required', 'string'],
            'meta_value' => ['required', 'string'],
        ]);

        try {
            $userMeta = UserMeta::create([
                'user_id' => $request->user()->id,
                'key' => $request->input('meta_key'),
                'value' => $request->input('meta_value'),
            ]);
            return get_success_response($userMeta, 201);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update user meta
     *
     * @param UpdateUserMetaRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUserMetaRequest $request, $id)
    {
        $this->validate($request, [
            'id' => ['required', 'integer', Rule::exists('user_metas', 'id')],
            'meta_key' => ['required', 'string'],
            'meta_value' => ['required', 'string'],
        ]);

        try {
            $userMeta = UserMeta::whereUserId(active_user())->whereId($id)->first();
            $userMeta->update([
                'key' => $request->input('meta_key'),
                'value' => $request->input('meta_value'),
            ]);
            return get_success_response($userMeta);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Delete user meta
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $this->validate($request, [
            'id' => ['required', 'integer', Rule::exists('user_metas', 'id')],
        ]);

        try {
            $userMeta = UserMeta::whereUserId(active_user())->whereId($id)->first();
            $userMeta->delete();
            return get_success_response(['message' => "Data deleted successfully"], 204);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 404);
        }
    }

    public function upload(Request $request)
    {
        try {
            $user = $request->user();
            $result = [];
            if ($request->hasfile('documents')) {
                foreach ($request->file('documents') as $key => $image) {
                    $path = $image->store("documents/$user->membership_id", 'r2');
                    $result[$key] = getenv("CLOUDFLARE_BASE_URL").$path;
                }
            }

            if ($request->hasFile('document')) {
                $avatarPath = $request->file('document')->store("documents/$user->membership_id", 'r2');
                $result['document'] = getenv("CLOUDFLARE_BASE_URL").$avatarPath;
            }

            return get_success_response($result);

        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function retriveUpload($fileEndpoint)
    {
        try {
            $file = base64_decode($fileEndpoint);
            $get = Storage::disk('r2')->get($file);
            return get_success_response([
                'file_path' => $fileEndpoint,
                'file_url' => $get
            ]);
        } catch (\Throwable $th) {
            return get_error_response([
                'error'=> $th->getMessage()
            ]);
        }
    }
}

