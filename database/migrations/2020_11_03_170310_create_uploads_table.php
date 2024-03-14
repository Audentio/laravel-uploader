<?php

use Audentio\LaravelBase\Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('content_type')->nullable()->index();
            // content_id removed with 1.2.0, moved to content_upload pivot table
            $table->string('content_field');
            $table->remoteId('user_id')->index()->nullable();

            $table->string('file_name');
            $table->string('file_hash');
            // file_path removed in 1.1.0, renamed to storage_path
            $table->string('file_type');
            $table->integer('file_size');

            $table->json('variants')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('associated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('uploads');
    }
};
