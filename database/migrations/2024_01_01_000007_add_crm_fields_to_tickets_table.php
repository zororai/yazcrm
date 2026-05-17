<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('mode_of_communication')->nullable()->after('description');
            $table->string('call_validity')->nullable()->after('mode_of_communication');
            $table->string('purpose_of_call')->nullable()->after('call_validity');
            $table->boolean('immediate_action_required')->default(false)->after('purpose_of_call');
            $table->unsignedTinyInteger('caller_age')->nullable()->after('immediate_action_required');
            $table->string('caller_gender')->nullable()->after('caller_age');
            $table->string('caller_marital_status')->nullable()->after('caller_gender');
            $table->string('key_pops')->nullable()->after('caller_marital_status');
            $table->string('province')->nullable()->after('key_pops');
            $table->string('district')->nullable()->after('province');
            $table->string('location')->nullable()->after('district');
            $table->boolean('is_repeat_caller')->default(false)->after('location');
            $table->string('project')->nullable()->after('is_repeat_caller');
            $table->text('services_requested')->nullable()->after('project');
            $table->string('second_service_requested')->nullable()->after('services_requested');
            $table->unsignedTinyInteger('number_of_services')->nullable()->after('second_service_requested');
            $table->string('referred_to')->nullable()->after('number_of_services');
            $table->boolean('uptake_confirmed')->default(false)->after('referred_to');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'mode_of_communication', 'call_validity', 'purpose_of_call',
                'immediate_action_required', 'caller_age', 'caller_gender',
                'caller_marital_status', 'key_pops', 'province', 'district',
                'location', 'is_repeat_caller', 'project', 'services_requested',
                'second_service_requested', 'number_of_services', 'referred_to',
                'uptake_confirmed',
            ]);
        });
    }
};
