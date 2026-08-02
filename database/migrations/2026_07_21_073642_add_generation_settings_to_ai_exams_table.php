<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_exams', function (Blueprint $table): void {
            $table->string('difficulty', 20)
                ->default('mixed')
                ->after('lesson_title');

            $table->json('question_types')
                ->nullable()
                ->after('difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('ai_exams', function (Blueprint $table): void {
            $table->dropColumn([
                'difficulty',
                'question_types',
            ]);
        });
    }
};
// وظيفته 
//إضافة مستوى الصعوبة وأنواع الأسئلة المطلوبة إلى الاختبار.