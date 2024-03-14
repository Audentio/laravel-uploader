<?php

use Audentio\LaravelBase\Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->integer('content_count')->after('content_type')->nullable()->index();
        });

        config('audentioUploader.uploadModel')::query()->chunkById(100, function ($uploads) {
            /** @var \Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface $upload */
            foreach ($uploads as $upload) {
                $upload->rebuildContentCount();
            }
        }, 'incr_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn('content_count');
        });
    }
};
