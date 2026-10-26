<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new
    /**
     * Creates the table that stores sandbox bookings.
     */
    class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sandbox_id')->constrained()->cascadeOnDelete();
                $table->string('analyst_name');
                $table->string('sample_reference');

                // Store UTC. Laravel's `datetime` cast serialises to UTC by default.
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');

                $table->timestamps();

                // ---------- Concurrency guards ----------
                // 1. Unique index on the exact same start time for the same sandbox
                //    stops the most common race (two identical inserts).
                $table->unique(['sandbox_id', 'starts_at']);

                // 2. Composite index makes the range-overlap query fast.
                $table->index(['sandbox_id', 'starts_at', 'ends_at']);
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::dropIfExists('bookings');
        }
    };
