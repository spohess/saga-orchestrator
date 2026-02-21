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
        Schema::create('saga_failure_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('saga_id');
            $table->string('failed_step');
            $table->string('exception_class');
            $table->text('exception_message');
            $table->json('executed_steps');
            $table->json('compensated_steps');
            $table->json('compensation_failures');
            $table->json('context_snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saga_failure_logs');
    }
};
