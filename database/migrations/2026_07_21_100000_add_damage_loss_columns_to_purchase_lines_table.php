<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->decimal('quantity_damaged', 22, 4)->default(0)->after('quantity');
            $table->decimal('quantity_lost', 22, 4)->default(0)->after('quantity_damaged');
            $table->string('damage_loss_reason')->nullable()->after('quantity_lost');
            $table->text('damage_loss_note')->nullable()->after('damage_loss_reason');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_damaged', 'quantity_lost', 'damage_loss_reason', 'damage_loss_note']);
        });
    }
};
