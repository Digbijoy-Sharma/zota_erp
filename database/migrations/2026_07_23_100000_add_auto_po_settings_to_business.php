<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-PO setting per store (business):
 *  - auto_po_frequency_days : how often (1-30 days) the scheduled
 *      auto-PO job raises a combined PO for this store. NULL means the
 *      store inherits the chain-wide default held on the template
 *      (lowest-id) business; NULL on the template too = auto-PO off.
 *
 * The target supplier is NOT stored here: auto-POs are raised to the
 * supplier the superadmin has already ASSIGNED to the store (a supplier
 * clone, contacts.common_supplier_id set), resolved at PO time.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('business', function (Blueprint $table) {
            if (! Schema::hasColumn('business', 'auto_po_frequency_days')) {
                $table->unsignedTinyInteger('auto_po_frequency_days')->nullable()->after('sell_return_period_days');
            }
        });
    }

    public function down()
    {
        Schema::table('business', function (Blueprint $table) {
            if (Schema::hasColumn('business', 'auto_po_frequency_days')) {
                $table->dropColumn('auto_po_frequency_days');
            }
        });
    }
};
