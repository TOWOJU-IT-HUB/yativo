<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserMeta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Bitso\app\Http\Controllers\BitsoController;

class GenerateClabe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     * @method generateClabe
     * @response clabe
     * @response string type 
     * @response string status 
     * @response string created_at 
     */
    public function handle(): void
    {
        $user = $this->user;
        $bitso = new BitsoController();
        $curl = $bitso->generateClabe();
        $clabe = (object)$curl;
        if(isset($clabe->success) && ($clabe->success == true)) {
            // add clabe to user meta data
            $data = $clabe->payload;
            $clabe_number = $data->clabe;
            
            $meta = new UserMeta();
            $meta->user_id = $user->id;
            $meta->key = "clabe_number";
            $meta->value = $clabe_number;
            $meta->save();
        }
    }
}
