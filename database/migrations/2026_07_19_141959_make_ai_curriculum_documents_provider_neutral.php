<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * نحذف الفهارس القديمة قبل تغيير أسماء الأعمدة؛
         * لأن أسماء هذه الفهارس ما زالت مرتبطة باسم OpenAI.
         */
        Schema::table('ai_curriculum_documents', function (Blueprint $table) {
            $table->dropUnique(
                'ai_curriculum_documents_openai_file_id_unique'
            );

            $table->dropIndex(
                'ai_documents_vector_store_index'
            );
        });

        /*
         * تغيير أسماء الأعمدة مع المحافظة على البيانات الموجودة.
         */
        Schema::table('ai_curriculum_documents', function (Blueprint $table) {
            $table->renameColumn(
                'openai_file_id',
                'provider_file_id'
            );

            $table->renameColumn(
                'openai_vector_store_id',
                'provider_vector_store_id'
            );
        });

        /*
         * إضافة المعلومات التي نحتاجها أثناء فهرسة الكتاب.
         */
        Schema::table('ai_curriculum_documents', function (Blueprint $table) {
            $table->string('ai_provider', 30)
                ->default('gemini')
                ->after('sha256');

            $table->string('provider_vector_store_document_id')
                ->nullable()
                ->after('provider_vector_store_id');

            $table->unique(
                'provider_file_id',
                'ai_documents_provider_file_unique'
            );

            $table->index(
                'provider_vector_store_id',
                'ai_documents_provider_store_index'
            );

            $table->unique(
                'provider_vector_store_document_id',
                'ai_documents_provider_store_document_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_curriculum_documents', function (Blueprint $table) {
            $table->dropUnique(
                'ai_documents_provider_file_unique'
            );

            $table->dropIndex(
                'ai_documents_provider_store_index'
            );

            $table->dropUnique(
                'ai_documents_provider_store_document_unique'
            );

            $table->dropColumn([
                'ai_provider',
                'provider_vector_store_document_id',
            ]);
        });

        Schema::table('ai_curriculum_documents', function (Blueprint $table) {
            $table->renameColumn(
                'provider_file_id',
                'openai_file_id'
            );

            $table->renameColumn(
                'provider_vector_store_id',
                'openai_vector_store_id'
            );
        });

        Schema::table('ai_curriculum_documents', function (Blueprint $table) {
            $table->unique(
                'openai_file_id',
                'ai_curriculum_documents_openai_file_id_unique'
            );

            $table->index(
                'openai_vector_store_id',
                'ai_documents_vector_store_index'
            );
        });
    }
};



    // وظيفة الحقول الجديدة:

    // الحقل	وظيفته
    // ai_provider	يسجل أن الكتاب تمت فهرسته باستخدام gemini
    // provider_file_id	رقم الملف لدى مزود AI
    // provider_vector_store_id	رقم مخزن البحث الذي يحتوي الكتب
    // provider_vector_store_document_id	رقم الكتاب داخل مخزن البحث
    // indexing_status	موجود مسبقًا ويبيّن pending/processing/indexed/failed