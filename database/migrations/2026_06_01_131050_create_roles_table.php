<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // slug: agent, supervisor, analyst …
            $table->string('display_name');            // human label
            $table->json('nav_permissions')->nullable(); // array of permission keys
            $table->boolean('is_system')->default(false); // system roles can't be deleted
            $table->timestamps();
        });

        // Seed built-in roles
        \DB::table('roles')->insert([
            ['name' => 'admin',      'display_name' => 'Admin',      'nav_permissions' => null,  'is_system' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'supervisor', 'display_name' => 'Supervisor', 'nav_permissions' => json_encode(['analytics','targets','by_project']), 'is_system' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'agent',      'display_name' => 'Agent',      'nav_permissions' => json_encode([]), 'is_system' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
