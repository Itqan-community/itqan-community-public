<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp;

/**
 * Static description of the Itqan Community MCP server.
 */
final class ServerCapabilities
{
    public const SERVER_NAME = 'itqan-community-mcp';

    public const SERVER_VERSION = '1.0.0';

    /**
     * Capabilities advertised during the MCP `initialize` handshake.
     */
    public static function getCapabilities(): array
    {
        return [
            'tools' => ['listChanged' => false],
            'resources' => ['subscribe' => false, 'listChanged' => false],
            'prompts' => ['listChanged' => false],
        ];
    }

    /**
     * Server identity advertised during the MCP `initialize` handshake.
     */
    public static function getServerInfo(): array
    {
        return [
            'name' => self::SERVER_NAME,
            'version' => self::SERVER_VERSION,
        ];
    }

    /**
     * Usage guidance returned to the model in the `initialize` result.
     *
     * Bilingual on purpose: the community is Arabic-first, while most MCP
     * clients reason and answer in English.
     */
    public static function getInstructions(): string
    {
        return <<<'TEXT'
# Itqan Community MCP · مجتمع إتقان

## English

You are connected to **Itqan Community** (https://community.itqan.dev), an Arabic-first
forum about Quranic technology, AI, speech recognition, and open standards.

- Search with **short keywords** of 1-4 words in `search_discussions` and `search_posts`
  (for example `tajweed`, `ASR`, `quranic data`). Do not paste long sentences or
  questions into the query; split the question into keywords instead.
- Call `list_tags` to discover category and topic tag slugs before filtering, such as
  `ai`, `asr`, `standards`, `quranic-data`, `imad`, and `developers-space`.
- Discussions support **branched/nested replies** (`reply_to_post_id` / `parent_id`) and
  community **voting** (`votes`, `likesCount`). Read the reply tree as a conversation,
  not as a flat list.
- Always **cite** the discussion title, the author, and a direct permalink in the form
  `https://community.itqan.dev/d/{discussion_id}/{post_number}`.
- Respect the server's rate limits and pagination; ask for more pages only when needed.

## العربية

أنت متصل بـ **مجتمع إتقان** (https://community.itqan.dev)، وهو منتدى عربي أولاً
يعنى بتقنيات القرآن الكريم والذكاء الاصطناعي والتعرف على الكلام والمعايير المفتوحة.

- ابحث بكلمات مفتاحية **قصيرة** من كلمة إلى أربع كلمات في `search_discussions`
  و`search_posts`، مثل `تجويد` أو `التعرف على الكلام` أو `بيانات قرآنية`. لا تضع
  سؤالاً كاملاً في خانة البحث، بل استخرج منه كلماته المفتاحية.
- استدعِ `list_tags` لمعرفة وسوم الأقسام والموضوعات قبل التصفية، مثل `ai` و`asr`
  و`standards` و`quranic-data` و`imad` و`developers-space`.
- تدعم المناقشات **الردود المتشعبة والمتداخلة** (`reply_to_post_id` / `parent_id`)
  و**التصويت** المجتمعي (`votes` و`likesCount`). اقرأ شجرة الردود بوصفها حواراً،
  لا قائمة مسطحة.
- اذكر دائماً **عنوان المناقشة** و**اسم الكاتب** و**رابطاً مباشراً** على الصيغة
  `https://community.itqan.dev/d/{discussion_id}/{post_number}`.
- راعِ حدود المعدل وتقسيم الصفحات في الخادم، ولا تطلب صفحات إضافية إلا عند الحاجة.
TEXT;
    }
}
