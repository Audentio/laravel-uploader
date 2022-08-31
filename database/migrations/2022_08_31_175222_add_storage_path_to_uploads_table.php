<?php

use Audentio\LaravelBase\Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AddStoragePathToUploadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $uploadModel = config('audentioUploader.uploadModel');

        Schema::table('uploads', function (Blueprint $table) {
            $table->string('storage_path')->after('user_id');
            $table->string('original_file_name')->after('file_name');
        });

        if (Schema::hasColumn('uploads', 'file_path')) {
            $uploadModel::chunkById(100, function ($uploads) {
                /** @var \Audentio\LaravelUploader\Models\Upload $upload */
                foreach ($uploads as $upload) {
                    // Values weren't set correctly previously, want to regenerate it completely rather
                    // than trying to use the old value
                    $upload->storage_path = $upload->getDefaultStoragePath();
                    $upload->original_file_name = $upload->file_name;
                    $upload->save();
                }
            }, 'incr_id');
        }

        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $uploadModel = config('audentioUploader.uploadModel');

        Schema::table('uploads', function (Blueprint $table) {
            $table->string('file_path')->after('file_hash');
        });

        $uploadModel::chunkById(100, function ($uploads) {
            /** @var \Audentio\LaravelUploader\Models\Upload $upload */
            foreach ($uploads as $upload) {
                try {
                    $upload->file_path = $upload->storage_path;
                    $upload->save();
                } catch (\Exception $e) {}
            }
        }, 'incr_id');

        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn(['storage_path', 'original_file_name']);
        });
    }
}
