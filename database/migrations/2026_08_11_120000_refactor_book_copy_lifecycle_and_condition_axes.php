<?php

use App\Models\BookCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('book_copies')) {
            return;
        }

        $this->allowTransitionalValues();

        DB::table('book_copies')
            ->where('condition_status', BookCopy::CONDITION_DAMAGED)
            ->update([
                'condition_status' => BookCopy::CONDITION_WORN,
                'status' => DB::raw($this->maintenanceUnlessFinalSql()),
            ]);

        DB::table('book_copies')
            ->whereIn('status', ['laisva', BookCopy::LEGACY_STATUS_LOANED])
            ->update(['status' => BookCopy::STATUS_IN_CIRCULATION]);

        $this->restrictToCanonicalValues();
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE book_copies MODIFY status ENUM('laisva','išduota','prarasta','tvarkoma','nurašyta') NOT NULL DEFAULT 'laisva'");
            DB::statement("ALTER TABLE book_copies MODIFY condition_status ENUM('nauja','gera','padėvėta','sugadinta') NOT NULL DEFAULT 'gera'");
        }
    }

    private function allowTransitionalValues(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE book_copies MODIFY status ENUM('laisva','išduota','ruošiama','apyvartoje','prarasta','tvarkoma','nurašyta') NOT NULL DEFAULT 'apyvartoje'");
        DB::statement("ALTER TABLE book_copies MODIFY condition_status ENUM('nauja','gera','padėvėta','sugadinta') NOT NULL DEFAULT 'gera'");
    }

    private function restrictToCanonicalValues(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE book_copies MODIFY status ENUM('ruošiama','apyvartoje','prarasta','tvarkoma','nurašyta') NOT NULL DEFAULT 'apyvartoje'");
        DB::statement("ALTER TABLE book_copies MODIFY condition_status ENUM('nauja','gera','padėvėta') NOT NULL DEFAULT 'gera'");
    }

    private function maintenanceUnlessFinalSql(): string
    {
        return "CASE WHEN status IN ('prarasta','nurašyta') THEN status ELSE 'tvarkoma' END";
    }
};
