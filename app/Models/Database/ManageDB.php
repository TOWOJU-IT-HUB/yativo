<?php

namespace App\Models\Database;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManageDB extends Model
{
    use HasFactory;

    /**
     * Backup the database to a specified path.
     *
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPass
     * @param string $dbName
     * @param string $backupPath
     * @return string
     */
    public function backupDatabase($dbHost, $dbUser, $dbPass, $dbName, $backupPath)
    {
        // Define the backup file name
        $backupFile = $backupPath . '/' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';

        // Create backup directory if it doesn't exist
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0777, true);
        }

        // Create the backup command
        $command = "mysqldump --host={$dbHost} --user={$dbUser} --password={$dbPass} {$dbName} > {$backupFile}";

        // Execute the command
        system($command, $output);

        if ($output === 0) {
            return "Backup completed successfully: $backupFile";
        } else {
            throw new \Exception("Backup failed with error code: $output");
        }
    }

    /**
     * Restore the database from a specified backup file.
     *
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPass
     * @param string $dbName
     * @param string $backupFile
     * @return string
     */
    public function restoreDatabase($dbHost, $dbUser, $dbPass, $dbName, $backupFile)
    {
        // Create the restore command
        $command = "mysql --host={$dbHost} --user={$dbUser} --password={$dbPass} {$dbName} < {$backupFile}";

        // Execute the command
        system($command, $output);

        if ($output === 0) {
            return "Database restored successfully from $backupFile";
        } else {
            throw new \Exception("Restore failed with error code: $output");
        }
    }

    /**
     * Drop all tables in the specified database.
     *
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPass
     * @param string $dbName
     * @return string
     */
    public function dropAllTables($dbHost, $dbUser, $dbPass, $dbName)
    {
        // Connect to the database using Laravel's DB facade
        DB::purge(); // Purge any existing database connections
        $connection = new \mysqli($dbHost, $dbUser, $dbPass, $dbName);

        if ($connection->connect_error) {
            throw new \Exception("Connection failed: " . $connection->connect_error);
        }

        // Get all table names
        $result = $connection->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_row()) {
                $table = $row[0];
                $connection->query("DROP TABLE IF EXISTS `$table`");
            }
            return "All tables dropped successfully.";
        } else {
            throw new \Exception("Error retrieving tables: " . $connection->error);
        }

        $connection->close();
    }
}
