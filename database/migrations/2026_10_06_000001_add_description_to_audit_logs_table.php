<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Human-readable detail for an audit entry (e.g. "Edited asset FA-0001: Name
// 'Old' → 'New'"), set by controllers that know what actually changed. Rows
// without it keep showing just the method/route/path as before.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('description')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
