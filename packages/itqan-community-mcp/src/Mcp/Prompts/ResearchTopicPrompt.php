<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Prompts;

use InvalidArgumentException;

/**
 * `research_quranic_tech_topic` — a reusable prompt for deep research in the
 * community, with source citations and the community's search conventions.
 */
class ResearchTopicPrompt
{
    public const NAME = 'research_quranic_tech_topic';

    /** Bound the caller supplied text so one prompt cannot grow unbounded. */
    private const MAX_TOPIC_LENGTH = 500;

    private const MAX_TAG_LENGTH = 100;

    public const DESCRIPTION = 'بحث معمّق في مجتمع إتقان حول موضوع تقني قرآني مع التوثيق بالمصادر (Deep research on a Quranic technology topic in Itqan Community)';

    /**
     * @return array{name: string, description: string, arguments: array<int, array{name: string, description: string, required: bool}>}
     */
    public function toPromptDefinition(): array
    {
        return [
            'name' => self::NAME,
            'description' => self::DESCRIPTION,
            'arguments' => [
                [
                    'name' => 'topic',
                    'description' => 'الموضوع أو التقنية القرآنية المراد البحث عنها (e.g., مصحف عماد، التسميع الصوتي، الرسم العثماني)',
                    'required' => true,
                ],
                [
                    'name' => 'tag',
                    'description' => 'وسم اختياري لتضييق البحث (e.g., ai, asr, standards, imad)',
                    'required' => false,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{description: string, messages: array<int, array{role: string, content: array{type: string, text: string}}>}
     */
    public function getMessages(array $arguments): array
    {
        $topic = isset($arguments['topic']) && is_scalar($arguments['topic'])
            ? mb_substr(trim((string) $arguments['topic']), 0, self::MAX_TOPIC_LENGTH, 'UTF-8')
            : '';

        if ($topic === '') {
            throw new InvalidArgumentException('The `topic` argument is required.');
        }

        $tag = isset($arguments['tag']) && is_scalar($arguments['tag'])
            ? mb_substr(ltrim(trim((string) $arguments['tag']), '#'), 0, self::MAX_TAG_LENGTH, 'UTF-8')
            : '';

        $tagLine = $tag !== ''
            ? "استخدم الوسم `{$tag}` لتضييق البحث (مرّر `tag` إلى `search_discussions`)."
            : 'لم يُحدَّد وسم؛ استدعِ `list_tags` أولاً لاختيار القسم أو الوسم الأنسب.';

        $text = <<<TEXT
أنت باحث في **مجتمع إتقان** (https://community.itqan.dev)، منتدى عربي أولاً يعني
بتقنيات القرآن الكريم والذكاء الاصطناعي والتعرف على الكلام وبيانات القرآن والمعايير المفتوحة.

**الموضوع المطلوب:** {$topic}

{$tagLine}

اتبع هذه الخطوات، واستخدم أدوات MCP المتاحة بدل الاعتماد على الذاكرة:

1. **حدّد المفردات.** استخرج من الموضوع 2-4 كلمات مفتاحية عربية، ثم 2-4 مقابلات
   إنجليزية أو أسماء مشاريع. لا تضع سؤالاً كاملاً في خانة البحث.
2. **اكتشف الوسوم.** استدعِ `list_tags` إن لم تكن تعرف `slug` القسم، ثم استخدم
   `search_discussions` بالوسم المناسب.
3. **ابحث مرتين على الأقل.** جرّب الكلمات العربية والإنجليزية، ثم أعد المحاولة
   بمتغيرات إملائية (بدون تشكيل، همزة/ألف، تاء مربوطة/هاء) إذا كانت النتائج قليلة.
4. **اقرأ أهم المناقشات كاملة.** استدعِ `get_discussion` لأفضل 2-4 مناقشات، واقرأ
   شجرة الردود المتشعبة (كل مشاركة تبيّن من تردّ عليه) لا القائمة المسطحة فقط.
5. **وسّع البحث داخل التعليقات.** استدعِ `search_posts` لإيجاد تعليقات محددة حول
   الموضوع، خصوصًا حين تكون الإجابة في رد لا في العنوان.
6. **تحقق من أصحاب الخبرة.** استدعِ `get_user_contributions` عند الحاجة للتحقق من
   مساهمات كاتب أو معرفة سياقه.
7. **قيّم بدقة.** الأصوات `votes` والإعجابات مؤشر على تقدير المجتمع، لا دليل على
   صحة المعلومة، خصوصًا في المسائل الشرعية أو التقنية الدقيقة. لا تنسب إلى المجتمع
   ما ورد في مشاركة محذوفة أو غير معتمدة.

**صيغة الإجابة المطلوبة (بالعربية):**
- ملخص تنفيذي في 3-5 أسطر.
- ما وجدته في المجتمع، مقسّمًا بحسب الموضوع الفرعي، مع ذكر اسم كل مناقشة وكاتبها.
- **المصادر:** قائمة بالروابط المباشرة على الصيغة
  `https://community.itqan.dev/d/{discussion_id}/{post_number}`، مع عنوان المناقشة
  واسم الكاتب لكل رابط.
- ما لم تجده أو كان محل خلاف بين المشاركين، وذكره بصراحة.
- إن لم توجد نتائج كافية، قل ذلك واقترح كلمات مفتاحية أو أقسامًا بديلة بدل التخمين.
TEXT;

        return [
            'description' => self::DESCRIPTION,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
            ],
        ];
    }
}
