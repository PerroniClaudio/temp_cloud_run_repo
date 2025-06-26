<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('parent_ticket_id')->nullable();
            $table->foreign('parent_ticket_id')->references('id')->on('tickets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('tickets', function (Blueprint $table) {
            //
            $table->dropForeign(['parent_ticket_id']);
            $table->dropColumn('parent_ticket_id');
        });
    }
};
