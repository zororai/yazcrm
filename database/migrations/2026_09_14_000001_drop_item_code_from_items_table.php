<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same reasoning as dropping Location.code / Store.code: the items table
// has no rows yet, and item_code is being dropped in favour of just name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique('items_item_code_unique');
            $table->dropColumn('item_code');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('item_code', 100)->nullable()->after('id');
        });
    }
};
