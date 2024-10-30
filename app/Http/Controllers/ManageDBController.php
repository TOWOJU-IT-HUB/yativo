<?php

namespace App\Http\Controllers;

use App\Models\Database\ManageDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class ManageDBController extends Controller
{
    protected $manageDB;

    public function __construct(ManageDB $manageDB)
    {
        $this->manageDB = $manageDB;
    }

    public function backup(Request $request)
    {
        $validated = $request->validate([
            'backupPath' => 'required|string',
        ]);

        $dbHost = Config::get('database.connections.mysql.host');
        $dbUser = Config::get('database.connections.mysql.username');
        $dbPass = Config::get('database.connections.mysql.password');
        $dbName = Config::get('database.connections.mysql.database');

        try {
            $message = $this->manageDB->backupDatabase(
                $dbHost,
                $dbUser,
                $dbPass,
                $dbName,
                $validated['backupPath']
            );
            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function restore(Request $request)
    {
        $validated = $request->validate([
            'backupFile' => 'required|string',
        ]);

        $dbHost = Config::get('database.connections.mysql.host');
        $dbUser = Config::get('database.connections.mysql.username');
        $dbPass = Config::get('database.connections.mysql.password');
        $dbName = Config::get('database.connections.mysql.database');

        try {
            $message = $this->manageDB->restoreDatabase(
                $dbHost,
                $dbUser,
                $dbPass,
                $dbName,
                $validated['backupFile']
            );
            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function dropAllTables(Request $request)
    {
        try {
            $dbHost = Config::get('database.connections.mysql.host');
            $dbUser = Config::get('database.connections.mysql.username');
            $dbPass = Config::get('database.connections.mysql.password');
            $dbName = Config::get('database.connections.mysql.database');

            $message = $this->manageDB->dropAllTables(
                $dbHost,
                $dbUser,
                $dbPass,
                $dbName
            );
            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
