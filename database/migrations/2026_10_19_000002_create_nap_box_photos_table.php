<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The photo of a NAP box, so the technician can recognise it on the pole. It is kept in the database (base64 text of a picture the
 * browser has already shrunk to about 1000 px) in a table of its own: the hosting has no permanent disk, and the list of NAP boxes
 * must never load the pictures.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nap_box_photos')) {
            return;
        }

        Schema::create('nap_box_photos', function (Blueprint $table) {
            $table->unsignedBigInteger('nap_box_id')->primary();
            $table->string('mime_type', 30);
            $table->longText('data');
            $table->timestamps();
            $table->foreign('nap_box_id')->references('id')->on('nap_boxes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nap_box_photos');
    }
};
