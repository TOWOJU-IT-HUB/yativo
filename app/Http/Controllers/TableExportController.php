<?php

// app/Http/Controllers/TableExportController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class TableExportController extends Controller
{
    public function index()
    {
        // Get all table names
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . DB::getDatabaseName();
        $tableNames = collect($tables)->pluck($key);

        return view('export-tables', compact('tableNames'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'tables' => 'required|array|min:1',
        ]);

        $tables = $request->tables;
        $fileName = 'exported_tables_' . now()->timestamp . '.sql';
        $sqlPath = storage_path("app/{$fileName}");

        // Export using mysqldump
        $tablesList = implode(' ', array_map('escapeshellarg', $tables));
        $db = config('database.connections.mysql');

        $cmd = sprintf(
            'mysqldump -u%s -p%s -h%s %s %s > %s',
            escapeshellarg($db['username']),
            escapeshellarg($db['password']),
            escapeshellarg($db['host']),
            escapeshellarg($db['database']),
            $tablesList,
            escapeshellarg($sqlPath)
        );

        exec($cmd);

        // Zip the SQL file
        $zipName = $fileName . '.zip';
        $zipPath = storage_path("app/{$zipName}");
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($sqlPath, $fileName);
            $zip->close();
        }

        // Clean up raw .sql file
        File::delete($sqlPath);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
