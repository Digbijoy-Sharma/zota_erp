<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks transactions that were created automatically by the auto-PO
 * engine (auto purchase_requisition headers in P2, and the combined
 * auto purchase_order in P3). Lets the engine find/maintain its own
 * open requisitions without ever touching manually-created ones, and
 * lets reports/the supplier portal distinguish auto vs manual docs.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'is_auto_generated')) {
                $table->boolean('is_auto_generated')->default(0)->after('type');
            }
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'is_auto_generated')) {
                $table->dropColumn('is_auto_generated');
            }
        });
    }
};
