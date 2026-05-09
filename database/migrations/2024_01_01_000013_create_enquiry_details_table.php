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
        Schema::create('enquiry_details', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email_address', 255)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('alt_phone_number', 20)->nullable();
            $table->text('message')->nullable();
            $table->text('pick_location')->nullable();
            $table->text('drop_location')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', [
                'Pending',
                'Processed',
                'Invalid',
                'Paid',
                'Payment Pending',
                'Completed',
            ])->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiry_details');
    }
};
