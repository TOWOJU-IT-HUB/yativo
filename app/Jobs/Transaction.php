<?php

namespace App\Jobs;

use App\Models\Transaction as ModelsTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Transaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $type, $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data, string $type)
    {
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $type = $this->type;
            $data = $this->data;
            foreach ($data as $key => $d) {
                ModelsTransaction::create([
                    'meta_id' => $data['id'],
                    'meta_key' => $key,
                    'meta_value' => $d,
                    'meta_type' => $type,
                ]);
            }
        } catch (\Throwable $th) {
            // continue;
        }

        // inititate payout
    }
}
