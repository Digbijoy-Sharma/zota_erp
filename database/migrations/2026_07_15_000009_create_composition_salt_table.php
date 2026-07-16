<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('composition_salt')) {
            Schema::create('composition_salt', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('composition_id');
                $table->unsignedInteger('salt_id');
                $table->timestamps();

                $table->index(['composition_id', 'salt_id']);
                $table->foreign('composition_id')->references('id')->on('compositions')->onDelete('cascade');
                $table->foreign('salt_id')->references('id')->on('salts')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('composition_salt');
    }
};
