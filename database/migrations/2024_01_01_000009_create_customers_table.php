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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->text('address')->nullable();
            $table->string('adharno', 12)->nullable()->unique();
            $table->string('pan_no', 10)->nullable()->unique();
            $table->string('phone_no', 15);
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->boolean('is_prime_user')->default(false);
            $table->boolean('is_red_flag')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

