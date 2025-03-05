<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropAllTables extends Command
{
    protected $signature = 'db:drop-all';
    protected $description = 'Drop all tables from the database';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Get the list of all tables in the database
        // $tables = DB::select('SHOW TABLES');
        // $tableKey = 'Tables_in_' . env('DB_DATABASE');

        // if (empty($tables)) {
        //     $this->info('No tables found in the database.');
        //     return;
        // }

        // // Disable foreign key checks before dropping tables
        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // foreach ($tables as $table) {
        //     $tableName = $table->$tableKey;
        //     Schema::dropIfExists($tableName);
        //     $this->info("Dropped table: $tableName");
        // }

        // // Re-enable foreign key checks after dropping tables
        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('All tables have been dropped successfully!');
    }
}
