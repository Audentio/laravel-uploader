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
        Schema::create('content_upload', function (Blueprint $table) {
            $table->morphs('content');
            $table->integer('display_order')->default(10);
            $table->remoteId('upload_id');
        });

        if (Schema::hasColumn('uploads', 'content_id')) {
            Schema::table('uploads', function (Blueprint $table) {
                $table->renameColumn('content_id', 'legacy_content_id');
                $table->renameColumn('display_order', 'legacy_display_order');
            });

            DB::table('uploads')
                ->whereNotNull('legacy_content_id')
                ->chunkById(100, function (\Illuminate\Support\Collection $uploads) {
                    $inserts = [];
                    $uploads->each(function ($upload) use (&$inserts) {
                        /** @var \Audentio\LaravelUploader\Models\Upload $upload */
                        $inserts[] = [
                            'content_type' => $upload->content_type,
                            'content_id' => $upload->legacy_content_id,
                            'display_order' => $upload->legacy_display_order,
                            'upload_id' => $upload->id,
                        ];
                    });

                    \DB::table('content_upload')->insert($inserts);
                }, 'incr_id');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('uploads', 'legacy_content_id')) {
            Schema::table('uploads', function (Blueprint $table) {
                $table->renameColumn('legacy_content_id', 'content_id');
                $table->renameColumn('legacy_display_order', 'display_order');
            });
        }
        Schema::dropIfExists('content_upload');
    }
};
