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
        Schema::create('id_sequence_tracker', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->unique();
            $table->unsignedBigInteger('last_assigned_id')->default(0);
            $table->unsignedBigInteger('current_max_id')->default(0);
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();
            
            $table->index(['table_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_sequence_tracker');
    }
}; 