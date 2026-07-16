<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the origin of a Contact row that was created from a
     * CommonSupplier assignment. Nullable, so this column is purely
     * informational and is safe to add to an existing populated table.
     * Unique-per-business so a single common supplier maps to at most
     * one Contact within a business.
     */
    public function up()
    {
        if (!Schema::hasColumn('contacts', 'common_supplier_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->unsignedInteger('common_supplier_id')->nullable()->after('contact_id');
                $table->index('common_supplier_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('contacts', 'common_supplier_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropIndex(['common_supplier_id']);
                $table->dropColumn('common_supplier_id');
            });
        }
    }
};
